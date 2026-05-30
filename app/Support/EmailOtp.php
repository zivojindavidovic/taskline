<?php

namespace App\Support;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 6-digit one-time codes for Cloud email verification.
 *
 * Stored in the cache (15-minute TTL) keyed by user id, so no migration is
 * required. In local dev (MAIL_MAILER=log) the code is also written to the log
 * and exposed to the verify screen as `dev_otp`.
 */
class EmailOtp
{
    public const TTL_MINUTES = 15;

    protected static function key(int $userId): string
    {
        return "email-otp:{$userId}";
    }

    /** Generate, store and email a fresh code. Returns the code. */
    public static function send(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::key($user->id), $code, now()->addMinutes(self::TTL_MINUTES));

        try {
            Mail::to($user->email)->send(new EmailVerificationOtpMail($code, $user->name));
        } catch (\Throwable $e) {
            Log::warning('Verification email failed: '.$e->getMessage());
        }

        if (app()->environment('local')) {
            Log::info("Taskline verification code for {$user->email}: {$code}");
        }

        return $code;
    }

    /** True only if the code matches the stored one (then it's consumed). */
    public static function verify(User $user, string $code): bool
    {
        $stored = Cache::get(self::key($user->id));

        if ($stored !== null && hash_equals($stored, trim($code))) {
            Cache::forget(self::key($user->id));

            return true;
        }

        return false;
    }

    /** The active code, for surfacing in local dev only. Null otherwise. */
    public static function peekForDev(User $user): ?string
    {
        return app()->environment('local')
            ? Cache::get(self::key($user->id))
            : null;
    }
}
