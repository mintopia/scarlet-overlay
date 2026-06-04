<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class PasskeyController extends Controller
{
    public function registerOptions(AttestationRequest $request)
    {
        return $request->userless()->toCreate();
    }

    public function register(AttestedRequest $request)
    {
        $request->save();

        return response()->json(['message' => 'Passkey registered.']);
    }

    public function loginOptions(AssertionRequest $request)
    {
        return $request->toVerify($request->validate(['email' => 'sometimes|email|string']));
    }

    public function login(AssertedRequest $request)
    {
        $user = $request->login();

        if ($user) {
            $request->session()->regenerate();

            return response()->json(['redirect' => '/admin/settings']);
        }

        return response()->json(['message' => 'Passkey authentication failed.'], 422);
    }

    public function destroy(Request $request, string $id)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->webAuthnCredentials()->where('id', $id)->delete();

        return response()->json(['message' => 'Passkey removed.']);
    }

    public function list(Request $request)
    {
        return $request->user()->webAuthnCredentials()
            ->select('id', 'alias', 'created_at', 'updated_at')
            ->get()
            ->makeVisible(['created_at', 'updated_at']);
    }
}
