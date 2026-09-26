<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setelah password benar, sesi ditandai mfa_pending (dipasang di AuthenticatedSessionController).
 * Selama flag ini ada, SEMUA halaman diblokir — hanya dua jalur keluar:
 *
 *  - User dengan 2FA aktif  -> wajib lolos halaman challenge (kode authenticator).
 *  - User tanpa 2FA         -> wajib menyelesaikan setup awal (pindai QR + konfirmasi).
 *
 * Logout tetap diizinkan agar user tidak terjebak.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || Session::get('mfa_pending') !== true) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            // 2FA aktif: sesi terkunci sampai kode kedua diverifikasi.
            if (! $request->routeIs('two-factor.challenge', 'two-factor.verify', 'logout')) {
                return $this->redirectTo($request, 'two-factor.challenge');
            }
        } else {
            // 2FA wajib tapi belum pernah dikonfirmasi: wajib setup sekarang.
            if (! $request->routeIs('two-factor.*', 'logout')) {
                return $this->redirectTo($request, 'two-factor.setup');
            }
        }

        return $next($request);
    }

    private function redirectTo(Request $request, string $route): Response
    {
        if ($request->expectsJson()) {
            abort(403, 'Verifikasi dua langkah diperlukan.');
        }

        return redirect()->route($route);
    }
}
