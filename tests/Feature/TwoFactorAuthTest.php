<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\TotpEngine;
use App\Services\TwoFactorManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Menguji alur MFA/TOTP end-to-end:
 *  - algoritma TOTP terhadap test vector resmi RFC 6238
 *  - login dua langkah (password -> challenge TOTP)
 *  - setup wajib untuk user tanpa 2FA
 *  - anti-replay, kode pemulihan sekali pakai
 *  - blokir user nonaktif (bug dari security audit)
 */
class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private TotpEngine $totp;
    private TwoFactorManager $mfa;

    private Role $roleAdmin;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totp = new TotpEngine;
        $this->mfa = new TwoFactorManager(new TotpEngine);

        $this->roleAdmin = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $this->unit = Unit::create(['name' => 'Unit Uji', 'code' => 'UUJ', 'is_active' => true]);
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Uji',
            'email' => 'uji@example.test',
            'password' => bcrypt('password-rahasia'),
            'role_id' => $this->roleAdmin->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ], $attrs));
    }

    /** Aktifkan 2FA untuk user persis seperti lewat UI (setup -> konfirmasi). */
    private function enableTwoFactor(User $user): void
    {
        $secret = $this->mfa->startSetup($user);
        $user->refresh();

        // PENTING: jangan memakai actingAs() yang menempel ke request berikutnya.
        $this->actingAs($user);
        Session::put('mfa_pending', true);

        $codes = $this->mfa->confirm($user, $this->totp->now($secret));
        $this->assertNotNull($codes);

        Session::forget('mfa_pending');
        $user->refresh();

        // Lepaskan state auth dari test harness agar POST /login berikutnya berperilaku nyata.
        $this->app['auth']->forgetGuards();
        auth('web')->logout();
        session()->flush();
    }

    // ------------------------------------------------------------------
    // 1. Algoritma: test vector resmi RFC 6238 (SHA-1, 8 digit)
    // ------------------------------------------------------------------

    public function test_totp_matches_official_rfc6238_test_vectors(): void
    {
        // Secret ASCII "12345678901234567890" dalam Base32.
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $vectors = [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];

        foreach ($vectors as [$timestamp, $expected]) {
            $counter = intdiv($timestamp, 30);
            $this->assertSame($expected, $this->totp->at($secret, $counter, 8), "Timestamp $timestamp");
        }
    }

    // ------------------------------------------------------------------
    // 2. Alur login dua langkah
    // ------------------------------------------------------------------

    public function test_user_without_two_factor_is_forced_through_setup(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia'])
            ->assertRedirect(route('dashboard', absolute: false)); // target awal; middleware yang mengunci

        // Semua halaman lain terkunci sampai setup selesai.
        $this->actingAs($user)
            ->withSession(['mfa_pending' => true])
            ->get('/dashboard')
            ->assertRedirect(route('two-factor.setup'));
    }

    public function test_login_completes_with_valid_totp(): void
    {
        $user = $this->makeUser();
        $this->enableTwoFactor($user);

        $code = $this->totp->now($this->mfa->getSecret($user));

        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia'])
            ->assertRedirect(route('dashboard', absolute: false)); // target awal; middleware yang mengunci

        $this->post('/two-factor', ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')->assertOk();
    }

    public function test_totp_challenge_blocks_wrong_code(): void
    {
        $user = $this->makeUser();
        $this->enableTwoFactor($user);

        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia']);

        $this->post('/two-factor', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        // Sesi masih terkunci.
        $this->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_same_totp_cannot_be_replayed(): void
    {
        $user = $this->makeUser();
        $this->enableTwoFactor($user);

        $code = $this->totp->now($this->mfa->getSecret($user));

        // Login pertama: berhasil (302 ke target awal), counter tersimpan.
        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia']);
        $this->post('/two-factor', ['code' => $code])->assertRedirect(route('dashboard', absolute: false));

        $this->post('/logout');

        // Login kedua dengan KODE YANG SAMA (dalam window ±30s): harus ditolak (anti-replay).
        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia']);
        $this->post('/two-factor', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_recovery_code_works_exactly_once(): void
    {
        $user = $this->makeUser();
        $this->enableTwoFactor($user);

        // Ambil kode pemulihan (hash tersimpan terenkripsi di DB; buat ulang pembandingnya
        // via konfirmasi kedua tidak praktis — jadi verifikasi lewat perilaku: kode
        // pemulihan yang benar bentuknya xxxx-xxxx dan disimpan saat confirm().
        // Kita regenerasi untuk mendapat daftar plaintext yang pasti.
        $codes = $this->mfa->regenerateRecoveryCodes($user);
        $this->assertCount(8, $codes);

        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia']);

        $this->post('/two-factor', ['code' => $codes[0]])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')->assertOk();

        // Kode yang sama tidak bisa dipakai lagi.
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia']);
        $this->post('/two-factor', ['code' => $codes[0]])->assertSessionHasErrors('code');

        // Sisa kode tinggal 7.
        $user->refresh();
        $this->assertSame(7, $this->mfa->remainingRecoveryCodes($user));
    }

    // ------------------------------------------------------------------
    // 3. Bug audit: user nonaktif tidak boleh bisa login
    // ------------------------------------------------------------------

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = $this->makeUser(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password-rahasia'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // 4. Manajemen secret & kode pemulihan
    // ------------------------------------------------------------------

    public function test_secret_and_recovery_codes_are_encrypted_at_rest(): void
    {
        $user = $this->makeUser();
        $this->enableTwoFactor($user);
        $user->refresh();

        // Tidak boleh ada plaintext secret di DB.
        $rawSecret = $this->mfa->getSecret($user);
        $this->assertStringNotContainsString($rawSecret, (string)$user->fresh()->getRawOriginal('two_factor_secret'));

        // JSON kode pemulihan terenkripsi.
        $this->assertStringNotContainsString('$2y$', (string)$user->getRawOriginal('two_factor_recovery_codes'));
    }
}
