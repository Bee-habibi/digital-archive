<x-guest-layout>
    <x-slot:auth_title>Buat Akun Baru</x-slot:auth_title>
    <x-slot:auth_sub>Lengkapi data berikut untuk mendaftarkan akun baru.</x-slot:auth_sub>
    <x-slot:auth_top_right>
        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
    </x-slot:auth_top_right>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="field">
            <label class="field-label" for="name">Nama Lengkap</label>
            <input id="name" type="text" name="name" class="input @error('name') is-invalid @enderror"
                value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Nama Anda">
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label class="field-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="input @error('email') is-invalid @enderror"
                value="{{ old('email') }}" required autocomplete="username" placeholder="nama@instansi.go.id">
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Klasifikasi organisasi: tingkat 1 (Unit Induk: Bapenda/UPTD) --}}
        <div class="field">
            <label class="field-label" for="parent_unit">Unit Induk</label>
            <select id="parent_unit" name="parent_unit" class="input @error('parent_unit') is-invalid @enderror" required>
                <option value="">-- Pilih Unit Induk (Bapenda / UPTD) --</option>
                @foreach($roots as $unit)
                    <option value="{{ $unit->id }}" @selected(old('parent_unit') == $unit->id)>{{ $unit->name }}</option>
                @endforeach
            </select>
            @error('parent_unit')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Klasifikasi organisasi: tingkat 2 (Sub Bagian/Seksi/Bidang di bawah Unit Induk) --}}
        <div class="field">
            <label class="field-label" for="child_unit">
                Sub Bagian / Seksi / Bidang
                <span style="font-weight:400;color:#6c757d">(di bawah Unit Induk yang dipilih)</span>
            </label>
            <select id="child_unit" name="child_unit" class="input @error('child_unit') is-invalid @enderror">
                <option value="">-- Tidak ada / langsung di bidang --</option>
            </select>
            @error('child_unit')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="input @error('password') is-invalid @enderror"
                required autocomplete="new-password" placeholder="Min. 8 karakter">
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label class="field-label" for="password_confirmation">Konfirmasi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                class="input @error('password_confirmation') is-invalid @enderror" required autocomplete="new-password"
                placeholder="Ulangi password">
        </div>

        <button type="submit" class="btn btn--primary auth-submit" style="justify-content:center">Daftar</button>
    </form>

    <div class="auth-main-bottom" style="margin-top:1rem">
        Pilih <strong>Unit Induk</strong> tempat Anda bertugas (Bapenda atau UPTD), lalu pilih
        <strong>Sub Bagian/Seksi/Bidang</strong> Anda. Arsip Anda otomatis tersimpan di folder unit tersebut.
    </div>

    @php
        $childrenByParent = [];
        foreach ($children as $child) {
            $childrenByParent[$child->parent_id][] = ['id' => $child->id, 'name' => $child->name];
        }
    @endphp

    <script>
        // Dropdown Sub Bagian/Seksi/Bidang terisi dinamis mengikuti Unit Induk yang dipilih.
        (function () {
            var data = @json($childrenByParent);
            var parentSel = document.getElementById('parent_unit');
            var childSel = document.getElementById('child_unit');
            if (!parentSel || !childSel) return;

            function fill() {
                var parentId = parentSel.value;
                childSel.innerHTML = '<option value="">-- Pilih sub unit --</option>';

                var list = (data && data[parentId]) ? data[parentId] : [];
                for (var i = 0; i < list.length; i++) {
                    var opt = document.createElement('option');
                    opt.value = list[i].id;
                    opt.textContent = list[i].name;
                    childSel.appendChild(opt);
                }

                // Pertahankan pilihan lama saat validasi gagal (old input).
                var old = {{ json_encode((int) old('child_unit', 0)) }};
                if (old) childSel.value = String(old);
            }

            parentSel.addEventListener('change', fill);
            fill();
        })();
    </script>
</x-guest-layout>
