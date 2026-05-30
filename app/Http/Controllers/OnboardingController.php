<?php

namespace App\Http\Controllers;

use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Support\Deployment;
use App\Support\EmailOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Guided first-run onboarding, ported from the auth.html prototype.
 *
 * Step order branches on the detected deployment:
 *   Cloud:       verify -> gate -> workspace -> done
 *   Self-hosted: workspace -> team -> done
 *
 * These routes sit behind `auth` only (never `onboarded`/`verified`) so users
 * mid-setup can reach them; the EnsureOnboarded middleware on the main app
 * routes pushes anyone without a workspace back into the right step here.
 */
class OnboardingController extends Controller
{
    private const COLORS = ['#4f46e5', '#0891b2', '#16a34a', '#7c3aed', '#d97706', '#dc2626', '#0d9488'];

    // ---- Step 1 (Cloud): verify email -------------------------------------

    public function showVerify(Request $request)
    {
        $user = $request->user();

        if (Deployment::isSelfHosted($request)) {
            return redirect()->route('onboarding.workspace');
        }
        if ($user->email_verified_at) {
            return $this->afterVerified($request);
        }

        return Inertia::render('Onboarding/VerifyEmail', [
            'email'   => $user->email,
            'dev_otp' => EmailOtp::peekForDev($user),
        ]);
    }

    public function verify(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = preg_replace('/\D/', '', $data['code']);

        if (! EmailOtp::verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => "That code isn't right — try again.",
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return $this->afterVerified($request);
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if (! $user->email_verified_at && Deployment::isCloud($request)) {
            EmailOtp::send($user);
        }

        return back()->with('success', 'A new code is on its way.');
    }

    // ---- Step 2 (Cloud): welcome + pending invitations --------------------

    public function showGate(Request $request)
    {
        $user = $request->user();

        if (Deployment::isSelfHosted($request)) {
            return redirect()->route('onboarding.workspace');
        }
        if (! $user->email_verified_at) {
            return redirect()->route('onboarding.verify');
        }
        if ($this->hasWorkspace($user)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Gate', [
            'invitations' => $this->pendingInvitations($user),
        ]);
    }

    public function acceptInvitation(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'invitation_id' => ['required', 'integer'],
        ]);

        $invitation = WorkspaceInvitation::with('workspace')
            ->where('id', $data['invitation_id'])
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->first();

        if (! $invitation || $invitation->isExpired()) {
            return redirect()->route('onboarding.gate')
                ->with('error', 'That invitation is no longer valid.');
        }

        $workspace = $invitation->workspace;

        if (! $workspace->users()->where('users.id', $user->id)->exists()) {
            $workspace->users()->attach($user->id, ['role' => $this->pivotRole($invitation->role)]);
        }
        $user->update(['current_workspace_id' => $workspace->id]);
        $invitation->delete();

        return redirect()->route('dashboard')->with('success', 'You joined '.$workspace->name.'.');
    }

    public function declineInvitation(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'invitation_id' => ['required', 'integer'],
        ]);

        WorkspaceInvitation::where('id', $data['invitation_id'])
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->delete();

        return back();
    }

    // ---- Step 3: create workspace -----------------------------------------

    public function showWorkspace(Request $request)
    {
        $user = $request->user();

        if (Deployment::isCloud($request) && ! $user->email_verified_at) {
            return redirect()->route('onboarding.verify');
        }
        if ($this->hasWorkspace($user)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Workspace', [
            'colors' => self::COLORS,
        ]);
    }

    public function storeWorkspace(Request $request)
    {
        $user    = $request->user();
        $cloud   = Deployment::isCloud($request);

        if ($cloud && ! $user->email_verified_at) {
            return redirect()->route('onboarding.verify');
        }
        if ($this->hasWorkspace($user)) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'color'           => ['required', Rule::in(self::COLORS)],
            'invites'         => ['array'],
            'invites.*.email' => ['nullable', 'email'],
            'invites.*.role'  => ['nullable', 'in:admin,member'],
        ]);

        $workspace = DB::transaction(function () use ($user, $data) {
            $workspace = Workspace::create([
                'name'     => $data['name'],
                'color'    => $data['color'],
                'owner_id' => $user->id,
            ]);
            $workspace->users()->attach($user->id, ['role' => 'owner']);
            $user->update(['current_workspace_id' => $workspace->id]);

            return $workspace;
        });

        // Cloud step 2: email invitations to teammates (deduped, self excluded).
        if ($cloud) {
            $this->sendWorkspaceInvites($request, $workspace, $data['invites'] ?? []);

            return redirect()->route('onboarding.done');
        }

        // Self-hosted: continue to direct team-account creation.
        return redirect()->route('onboarding.team');
    }

    // ---- Step 4 (Self-hosted): create team accounts directly ---------------

    public function showTeam(Request $request)
    {
        $user = $request->user();

        if (Deployment::isCloud($request)) {
            return redirect()->route('dashboard');
        }

        $workspace = $this->ownedWorkspace($user);
        if (! $workspace) {
            return redirect()->route('onboarding.workspace');
        }

        return Inertia::render('Onboarding/Team', [
            'workspaceName' => $workspace->name,
            'host'          => Deployment::resolve($request)['host'],
        ]);
    }

    public function storeTeam(Request $request)
    {
        $user = $request->user();

        if (Deployment::isCloud($request)) {
            return redirect()->route('dashboard');
        }

        $workspace = $this->ownedWorkspace($user);
        if (! $workspace) {
            return redirect()->route('onboarding.workspace');
        }

        $data = $request->validate([
            'members'            => ['required', 'array', 'min:1'],
            'members.*.name'     => ['nullable', 'string', 'max:255'],
            'members.*.email'    => ['required', 'email', 'distinct'],
            'members.*.role'     => ['required', 'in:admin,member'],
            'members.*.password' => ['required', 'string', 'min:8'],
        ]);

        $created = [];

        DB::transaction(function () use ($data, $workspace, &$created) {
            foreach ($data['members'] as $m) {
                $email = strtolower($m['email']);

                // Skip an email that already has an account or is already in.
                if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    continue;
                }

                $member = User::create([
                    'name'         => $m['name'] ?: explode('@', $email)[0],
                    'email'        => $email,
                    'password'     => Hash::make($m['password']),
                    'avatar_color' => self::COLORS[array_rand(self::COLORS)],
                ]);
                // No email verification on self-hosted instances.
                $member->forceFill(['email_verified_at' => now()])->save();

                $workspace->users()->syncWithoutDetaching([
                    $member->id => ['role' => $m['role']],
                ]);

                $created[] = [
                    'name'     => $member->name,
                    'email'    => $member->email,
                    'role'     => $m['role'],
                    'password' => $m['password'],
                ];
            }
        });

        // Re-render the same step with the one-time credentials reveal.
        return Inertia::render('Onboarding/Team', [
            'workspaceName' => $workspace->name,
            'host'          => Deployment::resolve($request)['host'],
            'created'       => $created,
        ]);
    }

    // ---- Done -------------------------------------------------------------

    public function showDone(Request $request)
    {
        if (! $this->hasWorkspace($request->user())) {
            return redirect()->route('onboarding.workspace');
        }

        $workspace = $this->ownedWorkspace($request->user())
            ?? $request->user()->currentWorkspace;

        return Inertia::render('Onboarding/Done', [
            'workspaceName' => $workspace?->name ?? 'Your workspace',
        ]);
    }

    // ---- Helpers ----------------------------------------------------------

    private function afterVerified(Request $request)
    {
        $user = $request->user();

        if ($this->hasWorkspace($user)) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding.gate');
    }

    private function hasWorkspace(User $user): bool
    {
        return Workspace::where('owner_id', $user->id)
            ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
    }

    private function ownedWorkspace(User $user): ?Workspace
    {
        return Workspace::where('owner_id', $user->id)->latest('id')->first();
    }

    private function pendingInvitations(User $user): array
    {
        return WorkspaceInvitation::with(['workspace', 'invitedBy'])
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->get()
            ->reject(fn ($inv) => $inv->isExpired())
            ->map(fn ($inv) => [
                'id'        => $inv->id,
                'workspace' => $inv->workspace?->only(['id', 'name', 'color']),
                'inviter'   => $inv->invitedBy?->name ?? 'Someone',
                'role'      => $inv->role,
            ])
            ->values()
            ->all();
    }

    private function sendWorkspaceInvites(Request $request, Workspace $workspace, array $invites): void
    {
        $seen = [strtolower($request->user()->email)];

        foreach ($invites as $row) {
            $email = strtolower(trim($row['email'] ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || in_array($email, $seen, true)) {
                continue;
            }
            $seen[] = $email;

            $invitation = $workspace->invitations()->updateOrCreate(
                ['email' => $email],
                [
                    'role'       => in_array(($row['role'] ?? 'member'), ['admin', 'member'], true) ? $row['role'] : 'member',
                    'projects'   => [],
                    'invited_by' => $request->user()->id,
                    'token'      => bin2hex(random_bytes(20)),
                    'expires_at' => now()->addDays(7),
                ]
            );

            try {
                Mail::to($email)->send(new WorkspaceInvitationMail($invitation, $workspace, $request->user()));
            } catch (\Throwable $e) {
                Log::warning('Invite email failed: '.$e->getMessage());
            }
        }
    }

    /** Map an invitation role onto the workspace_users enum (owner|admin|member). */
    private function pivotRole(?string $role): string
    {
        return in_array($role, ['admin', 'member'], true) ? $role : 'member';
    }
}
