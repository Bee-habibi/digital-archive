<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Implementasi TOTP (RFC 6238) + Base32 (RFC 4648) mandiri — tanpa dependensi eksternal.
 *
 * Terbukti terhadap 6 test vector resmi RFC 6238 (SHA-1, 8 digit) dan kompatibel
 * dengan Google Authenticator, Authy, Microsoft Authenticator, dan FreeOTP.
 * Komponen HOTP mengikuti RFC 4226 (dynamic truncation, counter 64-bit big-endian).
 */
class TotpEngine
{
    public const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Verifikasi kode 6 digit terhadap secret pada jendela waktu ±1 periode (±30 detik)
     * untuk menoleransi selisih jam kecil antara server dan HP user.
     *
     * @return int|null counter periode yang cocok (untuk anti-replay), atau null jika tidak cocok
     */
    public function verify(string $base32Secret, ?string $code, int $window = 1): ?int
    {
        if ($code === null || !preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $counter = intdiv(time(), 30);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->at($base32Secret, $counter + $i), $code)) {
                return $counter + $i;
            }
        }

        return null;
    }

    /** Kode TOTP saat ini untuk secret tertentu (dipakai test & diagnostik saja). */
    public function now(string $base32Secret, int $digits = 6): string
    {
        return $this->at($base32Secret, intdiv(time(), 30), $digits);
    }

    public function at(string $base32Secret, int $counter, int $digits = 6): string
    {
        $key = $this->decodeBase32($base32Secret);

        // Counter 64-bit big-endian (RFC 4226) — aman untuk timestamp melebihi 32-bit.
        $binary = pack('NN', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);

        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string)($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /** Secret acak 160-bit dalam Base32 (panjang tampilan 32 karakter, tanpa padding). */
    public function generateSecret(): string
    {
        $alphabet = self::ALPHABET;
        $out = '';
        for ($i = 0; $i < 32; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }

        return $out;
    }

    /** Kode pemulihan sekali pakai, format xxxx-xxxx, disimpan sebagai hash bcrypt. */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::lower(Str::random(4) . '-' . Str::random(4));
        }

        return $codes;
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $c) => password_hash($c, PASSWORD_BCRYPT), $codes);
    }

    /**
     * Cek kode pemulihan; jika cocok, kode tersebut langsung di-invalidate (sekali pakai).
     *
     * @return bool true jika cocok (dan kode sudah dibakar di array yang diberikan)
     */
    public function consumeRecoveryCode(array &$hashedCodes, string $input): bool
    {
        $input = Str::lower(preg_replace('/\s+/', '', $input));

        foreach ($hashedCodes as $i => $hash) {
            if (password_verify($input, $hash)) {
                unset($hashedCodes[$i]);
                $hashedCodes = array_values($hashedCodes);

                return true;
            }
        }

        return false;
    }

    /** URI otpauth:// untuk QR code & aplikasi authenticator. */
    public function otpauthUri(string $base32Secret, string $email, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
            . '?secret=' . $base32Secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public function decodeBase32(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $bits = '';
        $out = '';

        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $pos = strpos(self::ALPHABET, $b32[$i]);
            if ($pos === false) {
                throw new \InvalidArgumentException("Karakter Base32 tidak valid: {$b32[$i]}");
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }
}
