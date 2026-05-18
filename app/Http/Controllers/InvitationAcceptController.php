<?php

namespace App\Http\Controllers;

use App\Repositories\InvitationRepository;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationAcceptController extends Controller
{
    public function __construct(
        private InvitationRepository $repository,
        private InvitationService $invitations,
    ) {}

    /**
     * GET /invitations/{token}
     *
     * Three branches:
     *  - Guest, no account on this email → store token in session, redirect to register prefilled.
     *  - Guest, account exists           → store token in session, redirect to login prefilled.
     *  - Authenticated user              → if their email matches, accept; otherwise refuse.
     */
    public function show(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->repository->findByToken($token);

        if (!$invitation) {
            return redirect()->route('login')->with('error', 'This invitation link is invalid.');
        }

        if ($invitation->isExpired()) {
            return redirect()->route('login')->with('error', 'This invitation has expired. Ask the workspace owner to send a new one.');
        }

        $user = $request->user();

        if ($user) {
            if (strtolower($user->email) !== strtolower($invitation->email)) {
                return redirect()->route('dashboard')->with('error',
                    "This invitation was sent to {$invitation->email}. Sign in with that email to accept it.");
            }

            $this->invitations->accept($invitation, $user);

            return redirect()->route('dashboard')->with('success', 'Welcome to ' . $invitation->workspace->name . '.');
        }

        // Guest — stash the token in session so login/register can finalise after auth.
        $request->session()->put('pending_invitation_token', $token);

        $accountExists = \App\Models\User::where('email', $invitation->email)->exists();

        if ($accountExists) {
            return redirect()->route('login')->with('status', 'Sign in to accept your invitation to ' . $invitation->workspace->name . '.');
        }

        return redirect()->route('register', ['email' => $invitation->email])
            ->with('status', 'Create your account to join ' . $invitation->workspace->name . '.');
    }
}
