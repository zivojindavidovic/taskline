<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\Deployment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps not-yet-onboarded users out of the main app and pushes them into the
 * correct onboarding step. Existing users (anyone already in a workspace) pass
 * straight through, so this is invisible once setup is complete.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $hasWorkspace = Workspace::where('owner_id', $user->id)
            ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->exists();

        if ($hasWorkspace) {
            return $next($request);
        }

        if (Deployment::isCloud($request)) {
            return $user->email_verified_at
                ? redirect()->route('onboarding.gate')
                : redirect()->route('onboarding.verify');
        }

        return redirect()->route('onboarding.workspace');
    }
}
