# 📗 Panduan Frontend — Arsip Digital Bapenda Kaltara

> Target: app yang **ringan**, **interaktif**, dan **realtime** tanpa rombak ke SPA.
> Stack: **Blade + Alpine.js + Livewire 3 + Laravel Reverb (opsional, awalnya polling) + Chart.js + Tailwind**
>
> Ditulis oleh Buffy (Codebuff), 26 Sep 2026 — sesuai kondisi proyek saat ini:
> - PHP 8.4.25 (mod_php XAMPP), Laravel 13.32, MariaDB 10.4
> - Template Adminator custom (`public/template/adminator/`), layout utama: `resources/views/layouts/app.blade.php`
> - URL lokal: `http://localhost/digital-archive/public` (alias `/digital-archive` juga jalan)
> - Login: `superadmin@arsip.local` / `password123` (gerbang MFA setelahnya)

---

## 0. Prinsip dulu, biar nggak salah jalan

| Jangan | Kenapa | Pakai ini |
|---|---|---|
| ❌ React/Vue SPA + API terpisah | Bikin berat, double maintenance, app internal nggak butuh | Blade tetap jantung app |
| ❌ jQuery | Udah 2026 😄, bentrok dengan Alpine | Alpine.js (sudah ada di `package.json`) |
| ❌ CDN aneh-aneh / template ke-3 | Nambah beban & inkonsisten | Chart.js & FullCalendar **sudah ada** di `public/template/adminator/` |

**Aturan loading:** yang dinamis tabel → Livewire. Yang kecil (modal, dropdown, toast) → Alpine. Realtime → mulai dari polling,upgrade ke WebSocket (Reverb) kalau sudah mantap.

---

## 1. (Wajib) Nyalakan Vite — saat ini asset build belum dipasang di layout

`package.json` sudah lengkap (Vite 8, Tailwind, Alpine, laravel-vite-plugin), tapi layout
**belum memuat** `@vite(...)`, jadi Alpine/Tailwind sebenarnya belum aktif di halaman.

### 1a. Edit `resources/views/layouts/app.blade.php`

Di dalam `<head>` (mis. tepat di bawah `<meta name="csrf-token">` baris 8), tambahkan:

```blade
    @vite(['resources/css/app.css', 'resources/js/app.js'])
```

> Kalau muncul error "Vite manifest not found" di production-mode, itu wajar —
> jalankan `npm run build` (bawah) sebelum buka halaman.

### 1b. Build & jalankan dev server

```bash
cd C:\xampp\htdocs\digital-archive
npm install          # sekali saja (node_modules sudah ada, ini memastikan)
npm run dev          # mode pengembangan: HMR, buka app lewat browser biasa
# atau untuk pakai harian (build sekali, tanpa node jalan terus):
npm run build
```

> ⚠️ Vite dev server jalan di port 5173. Kalau port dipakai proses lain,
> matikan dulu atau ganti port di `vite.config.js`.

### 1c. (Opsional) Wire Tailwind v4 di Vite

`@tailwindcss/vite` sudah ada di devDependencies tapi belum didaftarkan.
Edit `vite.config.js` jadi:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
```

dan ganti isi `resources/css/app.css` jadi satu baris:

```css
@import "tailwindcss";
```

> Tailwind di sini fungsinya *polish* (kartu statistik, skeleton loading, spacing)
> — dia tidak menimpa Bootstrap/Adminator, karena class-nya di-purge cuma yang dipakai.

---

## 2. Livewire 3 — tabel arsip dinamis (search/filter/pagination tanpa reload)

### 2a. Install

```bash
composer require livewire/livewire
```

> Catatan: kalau network lagi bermasalah (packagist lambat), coba lagi nanti —
> sebelumnya ada jendela waktu network luar gagal. Livewire 3 otomatis inject
> CSS/JS-nya sendiri, **tidak perlu** `@livewireStyles`/`@livewireScripts` manual.

### 2b. Buat komponen

```bash
php artisan make:livewire archives.arsip-table
```

### 2c. `app/Livewire/Archives/ArsipTable.php`

Kolom mengikuti tabel `archives` yang sebenarnya:
`archive_number, folder_name, document_number, title, category_id, type_id,
unit_id, document_date, year, description, status, verified_at, ...`

```php
<?php

namespace App\Livewire\Archives;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ArsipTable extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $unit = '';

    #[Url(history: true)]
    public string $kategori = '';

    public int $perPage = 15;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatus() { $this->resetPage(); }
    public function updatedUnit()   { $this->resetPage(); }
    public function updatedKategori() { $this->resetPage(); }

    public function render()
    {
        $q = Archive::query()
            ->with(['category', 'type', 'unit'])
            // kalau pakai SoftDeletes di model Archive, aktifkan baris ini:
            // ->whereNull('deleted_at')
            ->when($this->search !== '', function ($query) {
                $s = '%' . $this->search . '%';
                $query->where(fn ($w) => $w
                    ->where('title', 'like', $s)
                    ->orWhere('archive_number', 'like', $s)
                    ->orWhere('document_number', 'like', $s)
                    ->orWhere('folder_name', 'like', $s));
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->unit !== '', fn ($query) => $query->where('unit_id', $this->unit))
            ->when($this->kategori !== '', fn ($query) => $query->where('category_id', $this->kategori))
            ->orderByDesc('created_at');

        return view('livewire.archives.arsip-table', [
            'archives' => $q->paginate($this->perPage)
                            ->through(fn ($a) => $a), // jaga paginate saat dipakai di view
            'units'      => Unit::orderBy('name')->get(['id', 'name']),
            'categories' => ArchiveCategory::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app');
    }
}
```

> Cek nama relasi di `app/Models/Archive.php` (mungkin `category`/`type`/`unit`
> sudah ada). Sesuaikan kalau beda.

### 2d. `resources/views/livewire/archives/arsip-table.blade.php`

```blade
<div class="d-card" style="background:#fff;border-radius:12px;padding:1rem;">
    {{-- Filter bar --}}
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" class="form-control" placeholder="Cari judul / no. arsip / no. dokumen…"
                   wire:model.live.debounce.350ms="search">
        </div>
        <div class="col-md-2">
            <select class="form-select" wire:model.live="status">
                <option value="">Semua status</option>
                @foreach(['draft','pending','verified'] as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model.live="unit">
                <option value="">Semua unit</option>
                @foreach($units as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" wire:model.live="kategori">
                <option value="">Semua kategori</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Skeleton saat loading --}}
    <div wire:loading.delay class="text-muted small mb-2">
        <span class="spinner-border spinner-border-sm"></span> Memuat…
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No. Arsip</th>
                    <th>Judul</th>
                    <th>Kategori</th>
                    <th>Unit</th>
                    <th>Tahun</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($archives as $a)
                    <tr wire:key="arsip-{{ $a->id }}">
                        <td class="text-nowrap">{{ $a->archive_number }}</td>
                        <td>
                            <a href="{{ route('archives.show', $a) }}">{{ $a->title }}</a>
                            <div class="small text-muted">{{ $a->document_number }}</div>
                        </td>
                        <td>{{ $a->category?->name }}</td>
                        <td>{{ $a->unit?->name }}</td>
                        <td>{{ $a->year }}</td>
                        <td><span class="badge text-bg-secondary">{{ $a->status }}</span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('archives.edit', $a) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        Tidak ada arsip yang cocok.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted">Total: {{ $archives->total() }} arsip</div>
        {{ $archives->links('pagination::bootstrap-5') }}
    </div>
</div>
```

### 2e. Pakai di halaman

Di `resources/views/archives/index.blade.php`, ganti tabel lama dengan:

```blade
<livewire:archives.arsip-table />
```

(`ArchiveController@index` tetap dipakai untuk halaman pembukanya.)

**Hasil:** ketik → hasil nyaring sambil jalan; ganti filter → tabel update tanpa reload;
state tersimpan di URL (bisa di-bookmark). Ini satu fitur yang paling kerasa
"aplikasi beneran".

---

## 3. Alpine.js — interaktivitas kecil yang bikin hidup

Alpine sudah di-import di `resources/js/app.js`. Setelah `@vite` aktif (Langkah 1),
langsung bisa dipakai di Blade mana pun.

### Contoh 1 — konfirmasi hapus yang manis (di halaman mana saja)

```blade
<div x-data="{ open: false }">
    <button class="btn btn-sm btn-outline-danger" @click="open = true">Hapus</button>

    <div x-cloak x-show="open" x-transition.opacity
         class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
         style="background:rgba(0,0,0,.45);z-index:2000">
        <div class="bg-white rounded-3 p-4" style="max-width:380px">
            <h5>Yakin hapus?</h5>
            <p class="text-muted small">Data yang dihapus tidak bisa dikembalikan.</p>
            <div class="d-flex gap-2 justify-content-end">
                <button class="btn btn-sm btn-secondary" @click="open = false">Batal</button>
                <form method="POST" action="{{ route('archives.destroy', $archive->id ?? 1) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-danger">Ya, hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
```

Tambahkan di `<head>` biar elemen `x-cloak` nggak kedip:

```html
<style>[x-cloak]{display:none!important}</style>
```

### Contoh 2 — global search cepat (Alpine + fetch)

```blade
<div x-data="quickSearch()" class="position-relative">
    <input class="form-control" placeholder="Cari arsip… (Ctrl+K)"
           x-model="q" @input.debounce.300ms="cari" @focus="open=true">
    <div x-cloak x-show="open && hasil.length" @click.outside="open=false"
         class="position-absolute bg-white border rounded-2 shadow mt-1 w-100" style="z-index:1050">
        <template x-for="h in hasil" :key="h.id">
            <a :href="h.url" class="d-block px-3 py-2 text-decoration-none border-bottom"
               x-text="h.title"></a>
        </template>
    </div>
</div>

<script>
function quickSearch() {
    return {
        q: '', open: false, hasil: [],
        get cari() {
            if (this.q.length < 2) { this.hasil = []; return }
            fetch('{{ url("/api/search") }}?q=' + encodeURIComponent(this.q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(d => { this.hasil = d.data ?? d; this.open = true })
        }
    }
}
</script>
```

Pendampingnya buat route API sederhana (di `routes/web.php`):

```php
Route::middleware('auth')->get('/api/search', function (\Illuminate\Http\Request $r) {
    $s = '%' . $r->string('q') . '%';
    return \App\Models\Archive::query()
        ->where('title', 'like', $s)
        ->orWhere('archive_number', 'like', $s)
        ->limit(8)
        ->get(['id', 'title', 'archive_number'])
        ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title . ' — ' . $a->archive_number,
                          'url' => route('archives.show', $a)]);
});
```

---

## 4. Realtime — mulai murah, naik kelas kalau perlu

### Tahap 1 (hari ini juga bisa): polling Livewire

Di komponen apa pun (misal widget "Aktivitas Terbaru"):

```blade
<div wire:poll.15s>   {{-- refresh data tiap 15 detik, hanya saat tab aktif --}}
    <livewire:activity-feed />
</div>
```

Bikin `php artisan make:livewire activity-feed` yang menampilkan
5–10 baris terbaru dari tabel `activity_logs` — selesai. Ringan, tanpa setup.

### Tahap 2 (nanti): Laravel Reverb + Echo — WebSocket beneran

```bash
composer require laravel/reverb
php artisan install:broadcasting      # buat config + .env otomatis
npm install --save-dev laravel-echo pusher-js
```

Jalankan server-nya di terminal terpisah saat development:

```bash
php artisan reverb:start        # default ws://localhost:8080
```

Event broadcast (contoh: notifikasi verifikasi arsip):

```php
// app/Events/ArsipDiverifikasi.php
class ArsipDiverifikasi implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Archive $archive) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('unit.' . $this->archive->unit_id)];
    }

    public function broadcastWith(): array
    {
        return ['title' => $this->archive->title,
                'number' => $this->archive->archive_number];
    }
}
```

Di panggilan verifikasi (controller), tinggal: `broadcast(new ArsipDiverifikasi($archive));`
atau pakai `->toBroadcast()` di model event.

Sisi frontend (di layout, setelah `@vite`):

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// lonceng notifikasi di topbar Adminator:
window.Echo.private(`unit.${window.APP_USER.unit_id}`)
    .listen('ArsipDiverifikasi', (e) => {
        window.dispatchEvent(new CustomEvent('notif-baru', { detail: e }));
    });
```

> Rencana akhir: badge lonceng di topbar naik + toast muncul saat arsip diverifikasi /
> user lain menambah arsip. Variabel `window.APP_USER` & `window.APP_BASE` sudah
> disiapkan layout (`2026.js` membacanya).

---

## 5. Halaman statistik dengan Chart.js (sudah ada di template)

Buat `StatistikController@index` yang ngirim agregat ke view:

```php
public function index()
{
    $perBulan = Archive::query()
        ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bulan, COUNT(*) as total")
        ->groupBy('bulan')->orderBy('bulan')->get();

    $perStatus = Archive::query()
        ->selectRaw('status, COUNT(*) as total')->groupBy('status')->get();

    $perUnit = Archive::query()
        ->join('units', 'units.id', '=', 'archives.unit_id')
        ->selectRaw('units.name, COUNT(*) as total')
        ->groupBy('units.name')->orderByDesc('total')->limit(8)->get();

    return view('statistik', compact('perBulan', 'perStatus', 'perUnit'));
}
```

Di view `statistik.blade.php` (Chart.js sudah diload Adminator):

```blade
<canvas id="chartBulan" height="90"></canvas>

@push('scripts')
<script>
new Chart(document.getElementById('chartBulan'), {
    type: 'line',
    data: {
        labels: @json($perBulan->pluck('bulan')),
        datasets: [{
            label: 'Arsip masuk',
            data: @json($perBulan->pluck('total')),
            tension: .35, fill: true
        }]
    }
});
</script>
@endpush
```

Tambah doughnut status & bar per-unit dengan pola yang sama. Daftarkan route +
link sidebar di `2026.js` (punya placeholder `data-shell-*`).

---

## 6. Optimasi biar nggak balik berat

1. **Load chart/fullcalendar per halaman.** Saat ini `vendor-chartjs.js` &
   `vendor-fullcalendar.js` ter-load global. Pindah pemanggilan `<script>`-nya
   dari layout ke halaman yang butuh (dashboard & statistik & kalender saja).
2. **`defer` semua script non-kritis** di layout (`<script defer src="…">`).
3. **Jangan dobel CSS.** Bootstrap (CDN) + Adminator `style.css` + Tailwind boleh
   berdampingan, tapi pastikan Tailwind di-purge (`npm run build`) dan pertimbangkan
   buang CDN Bootstrap di bulan depan (ganti ke bundle lokal biar offline).
4. **Cache route/config** kalau sudah stabil:
   `php artisan config:cache && php artisan route:cache && php artisan view:cache`
   (ingat: harus `php artisan optimize:clear` setiap ubah config/route).

---

## 7. Urutan kerja yang gue sarankan

- [ ] **Langkah 1** — @vite + `npm run build` (fondasi; 15 menit)
- [ ] **Langkah 2** — Livewire + `ArsipTable` (fitur paling terasa; 1–2 jam)
- [ ] **Langkah 3** — Alpine: modal hapus + quick search (1 jam)
- [ ] **Langkah 5** — Halaman statistik Chart.js (1–2 jam)
- [ ] **Langkah 4 Tahap 1** — `wire:poll` activity feed (30 menit)
- [ ] **Langkah 4 Tahap 2** — Reverb + lonceng notifikasi (setengah hari; opsional)
- [ ] **Langkah 6** — rapikan loading asset (30 menit)

## 8. Cheat sheet & troubleshooting

```bash
# sehari-hari
npm run dev                  # terminal A (development)
php artisan serve --port=8000  # TIDAK PERLU — Apache XAMPP sudah jalan
                              # buka: http://localhost/digital-archive/public

# kalau MySQL mati (misal habis restart komputer)
#   nyalakan dari XAMPP Control Panel → MySQL, atau:
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:/xampp/mysql/bin/my.ini --standalone

# reset semua cache Laravel
php artisan optimize:clear
```

- **"Vite manifest not found"** → `npm run build` (atau jalanin `npm run dev`).
- **composer gagal download** → network luar lagi rewel; ulangi beberapa saat lagi
  (mirror kadang butuh IPv4).
- **Halaman 500 setelah edit .env** → `php artisan config:clear`.
- **Blade error setelah edit layout** → `php artisan view:clear`.
