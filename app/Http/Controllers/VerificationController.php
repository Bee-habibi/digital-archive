<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ArchiveVerification;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    use LogsActivity;

    // Hanya Super User & Super Admin yang lewat sini (dikunci via route middleware role:...)
    public function index(Request $request)
    {
        $query = Archive::query()->where('status', Archive::STATUS_MENUNGGU_VERIFIKASI)
            ->with(['unit', 'category', 'type', 'creator']);

        if ($request->filled('unit_id')) $query->where('unit_id', $request->unit_id);

        $archives = $query->latest()->paginate(15)->withQueryString();

        return view('verification.index', compact('archives'));
    }

    public function process(Request $request, Archive $archive)
    {
        $this->authorize('verify', $archive);

        $data = $request->validate([
            'decision' => ['required', 'in:terverifikasi,perlu_perbaikan'],
            'notes' => ['nullable', 'string'],
        ]);

        $archive->update([
            'status' => $data['decision'],
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        ArchiveVerification::create([
            'archive_id' => $archive->id,
            'user_id' => Auth::id(),
            'status' => $data['decision'],
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
        ]);

        $this->logActivity('verify', 'archives', "Memverifikasi arsip #{$archive->archive_number} -> {$data['decision']}");

        return back()->with('success', 'Status verifikasi berhasil diperbarui.');
    }
}
