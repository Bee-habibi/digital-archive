<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $base = Archive::query()->visibleTo($user);

        $stats = [
            'total' => (clone $base)->count(),
            'menunggu_verifikasi' => (clone $base)->where('status', Archive::STATUS_MENUNGGU_VERIFIKASI)->count(),
            'terverifikasi' => (clone $base)->where('status', Archive::STATUS_TERVERIFIKASI)->count(),
            'perlu_perbaikan' => (clone $base)->where('status', Archive::STATUS_PERLU_PERBAIKAN)->count(),
            'per_kategori' => (clone $base)->selectRaw('category_id, count(*) as total')
                ->with('category:id,name')->groupBy('category_id')->get(),
            'per_tahun' => (clone $base)->selectRaw('year, count(*) as total')
                ->groupBy('year')->orderByDesc('year')->get(),
        ];

        if ($user->canAccessAllUnits()) {
            $stats['per_unit'] = (clone $base)->selectRaw('unit_id, count(*) as total')
                ->with('unit:id,name')->groupBy('unit_id')->get();
            $stats['aktivitas_terbaru'] = ActivityLog::with('user:id,name')
                ->latest('created_at')->limit(15)->get();
        }

        return view('dashboard.index', compact('stats'));
    }
}
