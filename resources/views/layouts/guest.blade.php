<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $auth_title ?? 'Masuk' }} - Arsip Digital Instansi</title>

    {{-- Terapkan tema SEBELUM CSS dimuat supaya tidak ada kedipan warna --}}
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('dash26-theme') || 'light');
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ asset('template/adminator/style.css') }}" rel="stylesheet">

    <style>
        /* Kotak notifikasi ringan untuk halaman auth (di luar cakupan Bootstrap) */
        .auth-alert {
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .auth-alert.success {
            background: var(--success-soft);
            color: var(--success);
        }

        .auth-alert.danger {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .auth-card .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .auth-card .field-label {
            color: var(--t-base);
            font-size: 12px;
            font-weight: 600;
        }

        .auth-main-top .brand-mini {
            align-items: center;
            display: flex;
            gap: 8px;
            color: var(--t-muted);
            font-size: 12.5px;
            font-weight: 600;
        }

        .auth-main-top .brand-mini .logo {
            background: var(--primary);
            border-radius: 7px;
            display: grid;
            height: 26px;
            place-items: center;
            width: 26px;
        }

        .auth-main-top .brand-mini .logo svg {
            height: 14px;
            width: 14px;
        }
    </style>
</head>

<body>
    <div class="auth-shell">
        <aside class="auth-aside">
            <div class="auth-brand">
                <div class="logo">
                    <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" fill="none">
                        <path fill="#ffffff"
                            d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z" />
                    </svg>
                </div>
                <div class="name">Arsip Digital</div>
            </div>

            <div class="auth-aside-body">
                <span class="auth-aside-eyebrow">BAPENDA PROVINSI KALIMANTAN UTARA</span>
                <h1>Transformasi pengelolaan arsip instansi</h1>
                <p>
                    Satu tempat untuk menyimpan, memverifikasi, dan menelusuri dokumen
                    resmi — rapi, aman, dan mudah diakses sesuai hak akses Anda.
                </p>
                <div class="auth-quote">
                    Digitalisasi arsip memangkas waktu pencarian dokumen dari hitungan
                    menit menjadi hitungan detik.
                    <div class="auth-quote-author">
                        <span class="av">BD</span>
                        Bapenda Provinsi Kalimantan Utara
                    </div>
                </div>
            </div>

            <div class="auth-aside-footer">
                <span>&copy; {{ date('Y') }} Bapenda Kaltara</span>
                <span>Versi 1.0</span>
            </div>
        </aside>

        <main class="auth-main">
            <div class="auth-main-top">
                <div class="brand-mini">
                    <span class="logo">
                        <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" fill="none">
                            <path fill="#ffffff"
                                d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z" />
                        </svg>
                    </span>
                    Arsip Digital Instansi
                </div>

                <div class="switch-link">{{ $auth_top_right ?? '' }}</div>
            </div>

            <div class="auth-card">
                @if (session('status'))
                    <div class="auth-alert success">{{ session('status') }}</div>
                @endif

                <h2>{{ $auth_title ?? '' }}</h2>
                <p class="sub">{{ $auth_sub ?? '' }}</p>

                {{ $slot }}
            </div>

            <div class="auth-main-bottom">
                &copy; {{ date('Y') }} Arsip Digital Instansi &middot; Bapenda Provinsi Kalimantan Utara
            </div>
        </main>
    </div>
</body>

</html>
