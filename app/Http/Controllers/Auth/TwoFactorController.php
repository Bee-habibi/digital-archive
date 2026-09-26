<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorManager;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Alur login dua langkah:
 *  1. Password benar (LoginRequest) -> user "dipinjam" via Auth::login, dibekukan flag session mfa_pending.
 *     Selama flag ini ada, middleware memblokir SEMUA halaman lain, dan controller ini hanya
 *     menerima permintaan verify/logout.
 *  2. Kode TOTP / kode pemulihan benar -> flag dibuka, user dianggap masuk penuh.
 */
class TwoFactorController extends Controller
{
    use LogsActivity;

    public function __construct(
        private TwoFactorManager $twoFactor,
    ) {}

    /** Halaman "Masukkan kode autentikator" (user dengan 2FA aktif). */
    public function challenge()
    {
        $user = Auth::user();
        if ($user && $user->two_factor_confirmed_at === null && $user->two_factor_secret !== null) {
            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor-challenge');
    }

    /** Verifikasi kode TOTP atau kode pemulihan dari form challenge. */
    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = Auth::user();
        abort_unless($user !== null && Session::get('mfa_pending'), 403);

        // Format asli diteruskan: TOTP 6 digit, atau kode pemulihan xxxx-xxxx.
        // Normalisasi spasi/tanda hubung ditangani di engine agar keduanya tetap dikenali.
        $code = trim($request->input('code'));

        if (! $this->twoFactor->verifyLoginChallenge($user, $code)) {
            $this->logActivity('failed', 'auth', 'Kode verifikasi dua langkah salah');

            return back()->withErrors(['code' => 'Kode tidak valid atau sudah kedaluwarsa. Coba lagi.']);
        }

        Session::forget('mfa_pending');
        $this->logActivity('login', 'auth', 'Verifikasi dua langkah berhasil');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Setup MFA pertama kali: tampilkan secret + QR untuk dipindai. */
    public function setup()
    {
        $user = Auth::user();

        if ($this->twoFactor->isEnabled($user)) {
            return redirect()->route('dashboard');
        }

        $secret = $this->twoFactor->startSetup($user);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrSvg' => $this->qrSvg($this->totpUri($secret, $user)),
        ]);
    }

    /** Konfirmasi setup dengan kode pertama dari aplikasi authenticator. */
    public function confirmSetup(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = Auth::user();

        $codes = $this->twoFactor->confirm($user, preg_replace('/\s/', '', $request->string('code')));

        if ($codes === null) {
            return back()->withErrors(['code' => 'Kode belum benar. Pastikan jam HP Anda akurat lalu coba lagi.']);
        }

        Session::forget('mfa_pending');

        $this->logActivity('enable', 'auth', 'Mengaktifkan verifikasi dua langkah');

        return view('auth.two-factor-recovery', ['codes' => $codes, 'regenerated' => false]);
    }

    /** Pengaturan 2FA hidup di halaman Profil (kartu "Verifikasi Dua Langkah"). */
    public function profile()
    {
        return redirect()->route('profile.edit');
    }

    /** Regenerasi kode pemulihan (yang lama hangus semua). */
    public function regenerateRecovery(Request $request)
    {
        $user = Auth::user();

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        if ($codes === null) {
            return redirect()->route('two-factor.profile');
        }

        $this->logActivity('update', 'auth', 'Regenerasi kode pemulihan dua langkah');

        return view('auth.two-factor-recovery', ['codes' => $codes, 'regenerated' => true]);
    }

    /** Nonaktifkan 2FA milik sendiri (harus menyertakan kode authenticator yang valid). */
    public function disable(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = Auth::user();

        $secret = $this->twoFactor->getSecret($user);

        if ($secret === null || $this->twoFactor->verifyLoginChallenge($user, $request->string('code')) === false) {
            return back()->withErrors(['code' => 'Kode tidak valid. 2FA tidak dinonaktifkan.']);
        }

        $this->twoFactor->disable($user);
        $this->logActivity('disable', 'auth', 'Menonaktifkan verifikasi dua langkah');

        return back()->with('success', 'Verifikasi dua langkah dinonaktifkan.');
    }

    /** Super Admin mereset 2FA user yang kehilangan perangkat (dari menu Users). */
    public function adminReset(Request $request, User $user)
    {
        $hadTwoFactor = $this->twoFactor->isEnabled($user);

        $this->twoFactor->disable($user);

        if ($hadTwoFactor) {
            $this->logActivity('update', 'users', "Reset 2FA user {$user->email} oleh Super Admin");
        }

        return back()->with('success', "2FA user {$user->email} direset. User akan diminta setup ulang saat login berikutnya.");
    }

    /** Halaman kode pemulihan dibuka tanpa data -> tampilkan peringatan, bukan error. */
    public function recoveryNotice()
    {
        return view('auth.two-factor-recovery', ['codes' => null, 'regenerated' => false]);
    }

    private function totpUri(string $secret, User $user): string
    {
        return $this->twoFactor->otpauthUri($secret, $user->email, config('app.name', 'Arsip Digital'));
    }

    /** Render QR (otpauth) sebagai SVG inline via bacon/bacon-qr-code. */
    private function qrSvg(string $uri): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220, 2),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
        );
        $writer = new \BaconQrCode\Writer($renderer);

        return $writer->writeString($uri);
    }
}
