# Security Assessment — Arsip Digital (digital-archive)

**Date:** 2026-09-16 · **Scope:** `http://localhost/digital-archive` (Laravel 13, PHP 8.5.10), Apache 2.4.58, MariaDB 10.4.32, phpMyAdmin 5.2.1
**Method:** Code review + live non-destructive testing from localhost and the machine's LAN address. All test users, records, and scripts created during testing were removed afterwards.

---

## STATUS UPDATE — 2026-09-22

- **Finding #1 (deactivated user keeps access): FIXED.** Login now rejects inactive accounts immediately (checked in `LoginRequest::authenticate()` right after credential verification). Additionally, a full **MFA layer (TOTP)** was added on top of passwords: login requires a 6-digit authenticator code (RFC 6238, verified against official test vectors), recovery codes are single-use, TOTP codes are replay-protected, secrets are encrypted at rest, and Super Admins can reset a user's 2FA. Both fixes are covered by automated tests (36 passing, including 8 new MFA tests).- Findings #2–#7 (platform hardening) remain open.

---

## Executive summary

The application layer is solid: authentication, authorization, IDOR protection, CSRF, SQL-injection resistance, and file-permission handling all held up against the tests run. The critical problems are in the **platform layer** — most notably a deactivated user remaining fully functional in the app (the only confirmed application bug), and an XAMPP-era MySQL setup (root with no password, listening on all interfaces, firewall-allowed from any remote address).

| # | Finding | Severity | Where |
|---|---------|----------|-------|
| 1 | Deactivated users keep full access until session ends | **HIGH (app bug)** | app/Http/Controllers/Auth/AuthenticatedSessionController.php |
| 2 | MySQL root with no password, 0.0.0.0 + firewall allow-any | HIGH (platform) | MariaDB config / firewall |
| 3 | APP_DEBUG=true with verbose error pages | MEDIUM | .env |
| 4 | No security headers (CSP, X-Frame-Options, HSTS) | MEDIUM | bootstrap/app.php |
| 5 | Plain HTTP only; HTTPS vhost is misconfigured self-signed | MEDIUM (LAN deployment) | httpd-ssl.conf |
| 6 | Verbose version banners | LOW | php.ini / httpd.conf |
| 7 | package.json / package-lock.json publicly readable | LOW | .htaccess |
| 8 | Archive numbers sequential & guessable | LOW (by design) | ArchiveController |
| 9 | phpMyAdmin 5.2.1 aging on PHP 8.5; blowfish_secret default | LOW (local-only) | phpMyAdmin config |

---

## What was tested and passed ✅

- **Authentication enforcement** — all 9 protected routes redirect unauthenticated requests to /login (302).
- **IDOR / cross-unit access** — an admin of unit 9 requesting archives of units 7 and 6 (read + edit) got **403** every time. The `ArchivePolicy` enforces `unit_id` matching at the object level, exactly as intended.
- **Role middleware** — `/users` 403 for both admin and super_user; **super_user could not create a Super Admin** (403) nor reset the Super Admin's password (403). The extra guard in `UserController::update` blocks super_user→super_admin escalation.
- **CSRF protection** — POST/PUT without a token → 419. Verified across login, profile, users, and archive endpoints.
- **SQL injection** — time-based (`SLEEP(3)`) and boolean-blind probes on `q`, `category_id`, and the login form showed no timing anomaly (~0.03s). Eloquent bindings everywhere; no raw query concatenation found in code review.
- **XSS** — no reflection of `<svg/onload>` payloads in login or archive search; no `{!! !!}` raw blade output anywhere in views.
- **Command injection / deserialization** — no `eval/exec/system/shell_exec/unserialize` sinks in `app/` or `routes/`.
- **File upload handling** — MIME whitelist (pdf/office/images), 20 MB cap, UUID filenames (extension-only, original name stored in DB), stored outside public/ and streamed via an authorized controller.
- **Sensitive files** — `.env`, `.env.example`, `.git`, `digital_archive.sql`, `artisan`, `composer.json`, `phpunit.xml` all **403** via the root .htaccess.
- **Directory listings** — none on /storage/, /build/, /template/, /vendor/.
- **Login throttling** — after 5 failures: "Too many login attempts. Please try again in 58 seconds." (works despite 302-based flow).
- **Cookie flags** — session cookie: `HttpOnly; SameSite=Lax`, encrypted (APP_KEY). XSRF cookie readable by JS by design.
- **Path traversal probes** — no traversal or template-injection effects observed.
- **server-status / server-info, phpMyAdmin, /xampp dashboard** — all restricted to `Require local` (LAN clients get 403). MariaDB additionally rejects remote root at the account level ("Host ... is not allowed to connect").
- **Deactivated-account login** — cannot log in fresh (session guard rejects inactive accounts).

## Findings in detail

### 1. HIGH — Deactivated users keep full access until their session ends
`EnsureUserHasRole` checks `is_active` per request, but routes guarded only by `auth` (dashboard, archives CRUD, profile) never re-check it. A user deactivated by a Super Admin keeps browsing and **can still create archives** until their session cookie dies (lifetime 120 min) — verified live: deactivated account created archive #25.
**Fix:** add a lightweight middleware that aborts/logs-out when `$user->is_active === false`, e.g.:

```php
// app/Http/Middleware/EnsureUserIsActive.php
public function handle($request, Closure $next)
{
    if ($request->user() && ! $request->user()->is_active) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        return redirect()->route('login')->withErrors(['email' => 'Akun Anda dinonaktifkan.']);
    }
    return $next($request);
}
```
Register it in the `web` group (or prepend to `auth`) in `bootstrap/app.php`.

### 2. HIGH — MySQL/MariaDB exposed to the network with root / no password
- `mysqld` listens on `0.0.0.0:3306` and `[::]:3306`.
- Windows Firewall rules `mysqld (Public, Inbound, Allow, Any→Any)` exist — the DB is reachable from the LAN.
- Root has **no password**; only the account's host restriction ("not allowed to connect" from LAN hosts) prevented a full remote takeover in testing.

**Fix (pick at least one, ideally all):**
- Set a strong root password: `mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '<strong>';"` and put it in `.env`.
- Bind to localhost only: in `my.ini` set `bind-address=127.0.0.1` (and remove `skip-networking=0` overrides), restart MySQL.
- Delete/flip the two `mysqld` Allow firewall rules to Block (or scope them to 127.0.0.1).

### 3. MEDIUM — APP_DEBUG=true in the deployed .env
Any 500 error returns a full Whoops trace: file paths, env values (secrets redacted but structure visible), stack frames. For a LAN-deployed internal app this leaks internals.
**Fix:** `APP_DEBUG=false` (keep `APP_ENV=production` once deployment config is final). Debug output then goes to `storage/logs/laravel.log` only.

### 4. MEDIUM — No hardening headers
Responses lack `Content-Security-Policy`, `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, and HSTS (see #5).
**Fix:** in `bootstrap/app.php` append middleware setting:
`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, and a CSP starting with `default-src 'self'` (relax for the bundled adminator template as needed).

### 5. MEDIUM — Plain HTTP on the LAN
Credentials (login form, session cookie) travel unencrypted over the office network; the 443 vhost exists but serves a `www.example.com` self-signed cert (warnings in error.log).
**Fix:** for an internal deployment, generate a self-signed cert with the machine's hostname and redirect 80→443, or at minimum restrict the app to trusted subnets.

### 6. LOW — Version banners
`Server: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.5.10` and `X-Powered-By: PHP/8.5.10` advertise exact versions (targeted-exploit value).
**Fix:** `expose_php=Off` in php.ini; `ServerTokens Prod` + `ServerSignature Off` in httpd.conf.

### 7. LOW — package.json / package-lock.json served (200)
Reveals the dependency tree to anyone; useful for attacker recon. Add them to the `<FilesMatch>` deny list in `C:\xampp\htdocs\digital-archive\.htaccess`.

### 8. LOW — Guessable archive numbers
`ARS/{unit}/{seq:003}/{year}` with per-unit counting is predictable (verified sequence). If archive numbers are meant to be semi-secret, they are not; the authorization layer does cover the actual data though. Acceptable as a design choice — worth documenting.

### 9. LOW — phpMyAdmin 5.2.1 on PHP 8.5
Local-only and auth-protected, but: aging release (deprecation notices were recently visible), `$cfg['blowfish_secret'] = 'xampp'` default, `auth_type=config` with `AllowNoPassword=true`. Once root gets a password (#2), switch `auth_type` to `cookie` and set a real blowfish secret.

---

## Cleanup performed
- 3 test users (super_admin/super_user/admin), their activity logs and sessions — deleted.
- Test archive created by the deactivated user — deleted (max archive id back to 24).
- Probe scripts, cookie jars, MySQL console logs — deleted.
- One test edit to archive #22 (title/status) — reverted to dump values.

## Recommended priority order
1. Fix #1 (deactivated-user middleware) — small code change, closes a real account-lifecycle gap.
2. MySQL hardening: password + bind 127.0.0.1 + firewall rules (#2).
3. `APP_DEBUG=false` (#3) and security headers (#4).
4. Banner removal, package.json deny, HTTPS decision (#5–#7).
