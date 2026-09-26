<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    // Super Admin: lihat semua log. Super User: hanya log terkait modul 'archives'
    // (dibatasi di query, bukan cuma disembunyikan di menu).
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ActivityLog::with('user:id,name,email');

        if (! $user->isSuperAdmin()) {
            $query->where('module', 'archives');
        }

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('module')) $query->where('module', $request->module);
        if ($request->filled('action')) $query->where('action', $request->action);
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

        $logs = $query->latest('created_at')->paginate(30)->withQueryString();

        return view('logs.index', compact('logs'));
    }

    // Hanya Super Admin (dikunci di route). Format: csv, xlsx (csv-compatible), json
    public function download(Request $request, string $format = 'csv'): StreamedResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $logs = ActivityLog::with('user:id,name,email')->latest('created_at')->get();

        // Pencatatan bahwa log pernah diunduh (audit trail atas audit trail-nya sendiri)
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'download',
            'module' => 'activity_logs',
            'description' => "Mengunduh activity log format {$format}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        if ($format === 'json') {
            return response()->streamDownload(function () use ($logs) {
                echo $logs->toJson(JSON_PRETTY_PRINT);
            }, 'activity_logs.json');
        }

        // csv & xlsx sama-sama diekspor sebagai CSV (bisa dibuka Excel langsung)
        $filename = $format === 'xlsx' ? 'activity_logs.xls' : 'activity_logs.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['User', 'Aksi', 'Modul', 'Deskripsi', 'IP Address', 'Waktu']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->user?->name ?? 'system',
                    $log->action,
                    $log->module,
                    $log->description,
                    $log->ip_address,
                    $log->created_at,
                ]);
            }
            fclose($out);
        }, $filename);
    }
}
