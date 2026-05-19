<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamInviteMail;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Team', [
            'members' => User::select('id', 'name', 'email', 'role', 'updated_at')
                ->orderByDesc('role')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'initials' => $u->initials,
                    'last_active' => $u->updated_at->diffForHumans(),
                ]),
            'invites' => Invite::select('id', 'email', 'created_at')->get(),
        ]);
    }

    public function invite(Request $request)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email', 'unique:invites,email'],
        ]);

        $invite = Invite::create([
            'email' => $validated['email'],
            'token' => Str::random(64),
            'invited_by' => $request->user()->id,
        ]);

        Mail::to($invite->email)->send(new TeamInviteMail($invite));

        return back()->with('success', 'Invite sent.');
    }

    public function resend(Request $request, Invite $invite)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        Mail::to($invite->email)->send(new TeamInviteMail($invite));

        return back()->with('success', 'Invite resent.');
    }

    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->isOwner() || $user->id === $request->user()->id) {
            abort(403);
        }

        $user->delete();

        return back()->with('success', 'Member removed.');
    }

    public function destroyInvite(Request $request, Invite $invite)
    {
        if (!$request->user()->isOwner()) {
            abort(403);
        }

        $invite->delete();

        return back()->with('success', 'Invite cancelled.');
    }
}
