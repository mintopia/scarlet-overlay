<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        $invite = Invite::where('token', $request->query('token'))->firstOrFail();

        return Inertia::render('Auth/Register', [
            'token' => $invite->token,
            'email' => $invite->email,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        $invite = Invite::where('token', $validated['token'])
            ->where('email', $validated['email'])
            ->firstOrFail();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->role = UserRole::Crew;
        $user->save();

        $invite->delete();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/admin/settings');
    }
}
