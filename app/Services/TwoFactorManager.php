<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * Manajemen 2FA (TOTP) per user: enable/disable, kode pemulihan, verifikasi.
 * Secret & kode pemulihan disimpan TERENKRIPSI (Laravel Crypt / APP_KEY AES-256).
 */
class TwoFactorManager
{
    public function __construct(
        private TotpEngine $totp,
    ) {}

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    /** MFA wajib untuk semua user (bisa dimatikan via system_settings key mfa_required = 0). */
    public function isRequired(): bool
    {
        return SystemSetting::get('mfa_required', '1') === '1';
    }

    /** Mulai setup: buat secret baru (belum aktif sampai dikonfirmasi dengan kode valid). */
    public function startSetup(User $user): string
    {
        $secret = $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret)])->save();

        return $secret;
    }

    public function getSecret(User $user): ?string
    {
        if ($user->two_factor_secret === null) {
            return null;
        }

        return Crypt::decryptString($user->two_factor_secret);
    }

    /** Konfirmasi setup dengan kode dari aplikasi authenticator; aktifkan 2FA + buat kode pemulihan. */
    public function confirm(User $user, string $code): ?array
    {
        $secret = $this->getSecret($user);
        if ($secret === null || $this->totp->verify($secret, $code) === null) {
            return null;
        }

        $codes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->totp->hashRecoveryCodes($codes))),
        ])->save();

        return $codes; // ditampilkan SEKALI kepada user
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_counter' => null,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): ?array
    {
        if (! $this->isEnabled($user)) {
            return null;
        }

        $codes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($this->totp->hashRecoveryCodes($codes))),
        ])->save();

        return $codes;
    }

    /** Verifikasi TOTP saat login dengan anti-replay (kode yang sama tak bisa dipakai dua kali).
     *  True juga jika kode pemulihan valid (kode terpakai otomatis dibakar). */
    public function verifyLoginChallenge(User $user, string $code): bool
    {
        $secret = $this->getSecret($user);

        if ($secret !== null) {
            $counter = $this->totp->verify($secret, $code);

            if ($counter !== null && $counter > (int)($user->two_factor_last_counter ?? -1)) {
                $user->forceFill(['two_factor_last_counter' => $counter])->save();

                return true;
            }
        }

        if ($user->two_factor_recovery_codes !== null) {
            $hashed = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

            if (is_array($hashed) && $this->totp->consumeRecoveryCode($hashed, $code)) {
                $user->forceFill([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashed)),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        if ($user->two_factor_recovery_codes === null) {
            return 0;
        }

        $hashed = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);

        return is_array($hashed) ? count($hashed) : 0;
    }

    /** URI otpauth:// untuk QR code & aplikasi authenticator. */
    public function otpauthUri(string $base32Secret, string $email, string $issuer): string
    {
        return $this->totp->otpauthUri($base32Secret, $email, $issuer);
    }

    /** Kode TOTP yang valid saat ini (khusus test otomatis). */
    public function currentCode(User $user): string
    {
        return $this->totp->at($this->getSecret($user));
    }
}
