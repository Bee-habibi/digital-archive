<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - Arsip Digital Instansi</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Vite: Tailwind + Alpine (Langkah 1 PANDUAN-FRONTEND.md) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Terapkan tema (light/dark) SEBELUM CSS dimuat supaya tidak ada kedipan warna.
         CSS Adminator hanya mendefinisikan variabel warna saat data-theme ada. --}}
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('dash26-theme') || 'light');
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>

    {{-- Font Adminator (Inter, Inter Tight, JetBrains Mono) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- Bootstrap dimuat DULUAN, lalu style.css Adminator.
         Urutan ini membuat Adminator SELALU menang untuk shell (sidebar/topbar/footer).
         Gaya khusus konten Bootstrap dijaga lewat blok <style> di bawah. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('template/adminator/style.css') }}" rel="stylesheet">

    <style>
        /* ===== Penyeimbang: shell Adminator selalu menang atas Bootstrap ===== */
        body {
            margin: 0;
            background: var(--bg-body);
            color: var(--t-base);
            font-family: Inter, system-ui, "Segoe UI", Ubuntu, Cantarell, "Noto Sans", -apple-system, Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;

            /* Alihkan variabel desain Bootstrap ke variabel Adminator
               agar SEMUA komponen Bootstrap ikut tema light/dark */
            --bs-body-color: var(--t-base);
            --bs-body-bg: var(--bg-body);
            --bs-border-color: var(--border);
            --bs-secondary-color: var(--t-muted);
            --bs-tertiary-bg: var(--bg-muted);
            --bs-emphasis-color: var(--t-base);
        }

        .shell {
            display: grid !important;
            grid-template-columns: 248px minmax(0, 1fr) !important;
            min-height: 100vh;
        }

        .shell > .main {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .d-sidebar {
            position: sticky !important;
            top: 0;
            height: 100vh;
            background: var(--bg-sidebar) !important;
            border-right: 1px solid var(--border) !important;
            display: flex !important;
            flex-direction: column;
            gap: 22px;
            padding: 22px 16px 18px !important;
            z-index: 1030;
        }

        .d-topbar {
            position: sticky !important;
            top: 0;
            z-index: 1020 !important;
            height: 60px !important;
            background: var(--overlay) !important;
            border-bottom: 1px solid var(--border) !important;
            backdrop-filter: saturate(140%) blur(10px);
            -webkit-backdrop-filter: saturate(140%) blur(10px);
        }

        .d-footer {
            margin-top: auto;
            padding: 24px 32px 28px !important;
            color: var(--t-muted) !important;
            font-size: 12px !important;
        }

        /* Menu sidebar: kunci gaya Adminator agar tidak tertimpa Bootstrap */
        .shell .nav-link {
            color: var(--t-muted);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 500;
            background: transparent;
        }

        .shell .nav-link:hover {
            background: var(--bg-hover);
            color: var(--t-base);
        }

        .shell .nav-link.is-active {
            background: var(--primary-soft);
            color: var(--primary);
            box-shadow: inset 3px 0 0 var(--primary);
        }

        .shell .nav-link > svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.75;
            flex-shrink: 0;
        }

        .shell .nav-label,
        .shell .workspace-role,
        .shell .brand-tag {
            color: var(--t-light);
        }

        .shell .brand-name,
        .shell .workspace-name {
            color: var(--t-base);
        }

        .shell .workspace-avatar {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: #fff;
        }

        .shell .dd-avatar-btn {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: #fff;
        }

        .shell .dd-menu {
            background: var(--bg-card);
            border: 1px solid var(--border);
        }

        .shell .dd-menu-item {
            color: var(--t-base);
        }

        .shell .dd-profile-name {
            color: var(--t-base);
        }

        .shell .dd-profile-email,
        .shell .dd-menu-item svg {
            color: var(--t-muted);
        }

        .shell .dd-menu-item.danger,
        .shell .dd-menu-item.danger svg {
            color: var(--danger);
        }

        .shell .crumbs {
            color: var(--t-muted);
        }

        .shell .crumbs .current {
            color: var(--t-base);
        }

        .shell .hamburger {
            color: var(--t-base);
        }

        .shell .icon-btn {
            color: var(--t-muted);
        }

        .shell .icon-btn:hover {
            background: var(--bg-hover);
            color: var(--t-base);
        }

        .shell .icon-btn > svg {
            fill: none;
            height: 17px;
            stroke: currentColor;
            stroke-width: 1.8;
            width: 17px;
        }

        .shell .drawer-backdrop {
            background: rgba(15, 23, 42, .5);
            position: fixed;
            inset: 0;
            z-index: 1040;
        }

        body.has-drawer-open {
            overflow: hidden;
        }

        /* Konten utama */
        main.content {
            padding: 1.5rem 2rem;
            color: var(--t-base);
        }

        main.content h1,
        main.content h2,
        main.content h3,
        main.content h4,
        main.content h5,
        main.content h6 {
            color: var(--t-base);
        }

        /* Bootstrap elements follow the Adminator theme (light & dark) */
        main.content .text-muted {
            color: var(--t-muted) !important;
        }

        main.content .table {
            --bs-table-color: var(--t-base);
            --bs-table-bg: transparent;
            color: var(--t-base);
        }

        main.content .table-light {
            --bs-table-bg: var(--bg-muted);
            --bs-table-color: var(--t-muted);
            border-color: var(--border);
        }

        main.content .form-control,
        main.content .form-select {
            background-color: var(--bg-card);
            border-color: var(--border);
            color: var(--t-base);
        }

        main.content .form-control:focus,
        main.content .form-select:focus {
            background-color: var(--bg-card);
            border-color: var(--primary);
            color: var(--t-base);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        main.content .form-control::placeholder {
            color: var(--t-light);
        }

        main.content .list-group-item {
            background-color: transparent;
            border-color: var(--border-soft);
            color: var(--t-base);
        }

        main.content .pagination .page-link {
            background-color: var(--bg-card);
            border-color: var(--border);
            color: var(--t-muted);
        }

        main.content .pagination .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--t-base);
        }

        .modal-header,
        .modal-footer {
            border-color: var(--border-soft);
        }

        /* Badge status arsip (dipakai di halaman arsip) */
        /* Warna teks DISET EKSPLISIT: tanpa ini, mode gelap Adminator mewarisi
           warna font ke badge dan jadi tabrakan (mis. oranye di atas oranye). */
        .badge-status-draft {
            background: #6c757d;
            color: #ffffff !important;
            border: 1px solid #5c636a;
        }

        .badge-status-menunggu_verifikasi {
            background: #fd7e14;
            color: #ffffff !important;
            border: 1px solid #c85f02;
        }

        .badge-status-terverifikasi {
            background: #198754;
            color: #ffffff !important;
            border: 1px solid #14713f;
        }

        .badge-status-perlu_perbaikan {
            background: #dc3545;
            color: #ffffff !important;
            border: 1px solid #b02a37;
        }

        .badge-status-diarsipkan {
            background: #0d6efd;
            color: #ffffff !important;
            border: 1px solid #0a58ca;
        }

        /* ===== Responsif: ikuti perilaku Adminator ===== */
        @media (max-width: 1100px) {
            .shell {
                grid-template-columns: 72px minmax(0, 1fr) !important;
            }

            .d-sidebar {
                padding: 20px 10px !important;
            }

            .d-sidebar .brand-tag,
            .d-sidebar .brand-text,
            .d-sidebar .nav-label,
            .d-sidebar .nav-link .chev,
            .d-sidebar .nav-link > span:not(.nav-badge),
            .d-sidebar .nav-submenu,
            .d-sidebar .workspace-text {
                display: none;
            }

            .d-sidebar .brand,
            .d-sidebar .nav-link {
                justify-content: center;
            }

            .d-sidebar .nav-link {
                padding: 10px;
            }
        }

        @media (max-width: 720px) {
            .shell {
                display: block !important;
            }

            .d-sidebar {
                position: fixed !important;
                top: 0;
                left: 0;
                width: 280px;
                padding: 22px 16px 18px !important;
                transform: translateX(-100%);
                transition: transform .24s cubic-bezier(.2, .7, .2, 1);
                box-shadow: 0 12px 40px -8px rgba(0, 0, 0, .4);
            }

            .d-sidebar .brand-tag,
            .d-sidebar .brand-text,
            .d-sidebar .nav-label,
            .d-sidebar .nav-link > span:not(.nav-badge),
            .d-sidebar .workspace-text {
                display: revert;
            }

            .d-sidebar .brand,
            .d-sidebar .nav-link {
                justify-content: flex-start;
            }

            .d-sidebar .nav-link {
                padding: 9px 12px;
            }

            body.has-drawer-open .d-sidebar {
                transform: translateX(0);
            }

            .hamburger {
                display: inline-flex !important;
            }

            .d-topbar {
                padding: 0 12px !important;
            }

            main.content {
                padding: 20px 16px 16px;
            }

            .d-footer {
                padding: 20px 16px !important;
            }
        }

        /* ===== Mobile-friendly: ponsel & tablet kecil =====
           Aplikasi ini dirancang tetap nyaman dipakai dari mana saja (HP),
           bukan hanya di depan komputer/laptop. */

        /* Filter Livewire: form kontrol melebar penuh di layar sempit */
        @media (max-width: 767.98px) {
            .livewire-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .livewire-filters .form-control,
            .livewire-filters .form-select {
                width: 100%;
                min-width: 0 !important;
                max-width: none !important;
                /* Target sentuh nyaman (rekomendasi aksesibilitas ~44px) */
                min-height: 44px;
                font-size: 16px; /* cegah auto-zoom di iOS Safari */
            }

            .livewire-filters .filter-actions {
                margin-left: 0 !important; /* .ms-auto dimatikan di HP */
            }

            .livewire-filters .filter-actions .btn {
                flex: 1;
                min-height: 44px;
            }

            /* Aksi baris (Detail/Download dll.) jadi tombol lebar yang mudah disentuh */
            .table-card .td-actions .btn {
                min-height: 38px;
                padding: 6px 18px;
            }
        }

        /* Tabel -> kartu di ponsel (≤767px): tanpa scroll horizontal.
           Setiap <td> punya data-label yang tampil sebagai keterangan. */
        @media (max-width: 767.98px) {
            .table-card thead {
                display: none;
            }

            .table-card,
            .table-card tbody,
            .table-card tr,
            .table-card td {
                display: block;
                width: 100%;
            }

            .table-scroll {
                overflow-x: visible; /* scroll tidak dibutuhkan lagi */
            }

            .table-card tr {
                border: 1px solid var(--border);
                border-radius: 10px;
                background: var(--bg-card);
                padding: 10px 14px;
                margin-bottom: 12px;
            }

            .table-card td {
                border: 0;
                padding: 3px 0;
                text-align: left;
            }

            .table-card td::before {
                content: attr(data-label);
                display: block;
                font-size: 10.5px;
                font-weight: 600;
                letter-spacing: .04em;
                text-transform: uppercase;
                color: var(--t-muted);
                margin-bottom: 1px;
            }

            /* Kolom pertama (No. Arsip) jadi judul kartu */
            .table-card td:first-child {
                padding-bottom: 6px;
                margin-bottom: 6px;
                border-bottom: 1px dashed var(--border-soft);
            }

            .table-card td:first-child::before {
                color: var(--t-light);
            }

            .table-card td.td-actions::before {
                content: none; /* aksi tidak butuh label */
            }

            .table-card td.td-actions {
                padding-top: 8px;
                margin-top: 6px;
                border-top: 1px dashed var(--border-soft);
            }

            /* Tabel kecil di dashboard tetap ringkas */
            main.content .table-sm td {
                display: flex;
                justify-content: space-between;
                gap: 12px;
            }

            /* Header halaman & baris aksi agar tidak meluber */
            main.content .d-flex.justify-content-between {
                flex-wrap: wrap;
                gap: 8px;
            }

            /* Modal tetap dalam layar (Bootstrap default pun, dijamin di sini) */
            .modal-dialog {
                margin: .5rem;
            }

            /* Paginasi Livewire/Bootstrap enteng di layar kecil */
            .pagination {
                flex-wrap: wrap;
                margin-bottom: 0;
            }

            .pagination .page-link {
                min-height: 40px;
                min-width: 40px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* Ponsel sangat kecil (≤380px): badge boleh mengecil sedikit */
        @media (max-width: 380px) {
            .badge-status-draft,
            .badge-status-menunggu_verifikasi,
            .badge-status-terverifikasi,
            .badge-status-perlu_perbaikan,
            .badge-status-diarsipkan {
                font-size: 11px;
                padding: 3px 8px;
            }
        }
    </style>

    <script>
        // Data user yang login - dibaca oleh template/adminator/2026.js
        // untuk nampilin nama asli & nyaring menu sidebar sesuai role
        window.APP_USER = {
            name: @json(auth()->user()->name),
            initials: @json(collect(explode(' ', auth()->user()->name))->map(fn($w) => strtoupper($w[0] ?? ''))->take(2)->join('')),
            role: @json(auth()->user()->role->name ?? '-'),
        };

        // Base URL aplikasi, dihitung dari request sehingga selalu benar,
        // baik lewat `php artisan serve` maupun Apache (/digital-archive/public).
        // Dipakai 2026.js agar semua link sidebar/topbar mengarah ke path yang benar.
        window.APP_BASE = @json(url('/'));
    </script>
</head>

@php
    // Menentukan menu sidebar mana yang aktif (data-active) berdasarkan route saat ini
    $activeKey = match (true) {
        request()->routeIs('archives.create') => 'archives-create',
        request()->routeIs('archives.*') => 'archives',
        request()->routeIs('verification.*') => 'verification',
        request()->routeIs('logs.*') => 'logs',
        request()->routeIs('units.*') => 'units',
        request()->routeIs('archive-categories.*') => 'categories',
        request()->routeIs('users.*') => 'users',
        request()->routeIs('profile.*') => 'profile',
        default => 'dashboard',
    };
@endphp

<body data-active="{{ $activeKey }}" data-crumbs="Arsip Digital | @yield('title', 'Dashboard')">
    <div class="shell">
        <div data-shell-sidebar></div>
        <div class="main">
            <div data-shell-topbar></div>
            <main class="content">
                @if (session('success'))
                    <div class="alert success">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                        </div>
                        <div class="body">{{ session('success') }}</div>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert danger">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </div>
                        <div class="body">{{ session('error') }}</div>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert danger">
                        <div class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                        </div>
                        <div class="body">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
            <div data-shell-footer></div>
        </div>
    </div>

    <script src="{{ asset('template/adminator/runtime.js') }}"></script>
    <script src="{{ asset('template/adminator/vendor-fullcalendar.js') }}"></script>
    <script src="{{ asset('template/adminator/vendor-chartjs.js') }}"></script>
    <script src="{{ asset('template/adminator/vendors.js') }}"></script>
    <script src="{{ asset('template/adminator/2026.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>
