<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Deployment;
use App\Support\EmailOtp;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * Registration no longer auto-creates a workspace or jumps to the
     * dashboard. Instead it starts the guided onboarding flow, which branches
     * on the detected deployment (mirrors the auth.html prototype):
     *
     *   Cloud:        register -> verify email (OTP) -> gate -> workspace -> done
     *   Self-hosted:  register (admin) -> workspace -> team accounts -> done
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $mode = Deployment::mode($request);

        // Self-hosted has no email infrastructure to lean on — the first admin
        // account is trusted immediately, so we skip the verification step.
        if ($mode === 'self-hosted') {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        event(new Registered($user));

        Auth::login($user);

        // Arrived via an invitation link: the invite proves the email is theirs,
        // so honour it directly (this also lands them inside a workspace).
        if ($token = $request->session()->pull('pending_invitation_token')) {
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return redirect()->route('invitations.accept', ['token' => $token]);
        }

        if ($mode === 'cloud') {
            EmailOtp::send($user);

            return redirect()->route('onboarding.verify');
        }

        return redirect()->route('onboarding.workspace');
    }
}
