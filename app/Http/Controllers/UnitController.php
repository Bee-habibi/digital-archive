<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use LogsActivity;

    // Khusus Super Admin (dikunci lewat route middleware)
    public function index()
    {
        $units = Unit::withCount('archives', 'users')->get();
        return view('units.index', compact('units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:units,code'],
            'parent_id' => ['nullable', 'exists:units,id'],
            'level' => ['required', 'integer', 'min:1', 'max:2'],
            'description' => ['nullable', 'string'],
        ]);

        Unit::create($data);
        $this->logActivity('create', 'units', "Menambah unit kerja {$data['name']}");

        return back()->with('success', 'Unit kerja berhasil ditambahkan.');
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:units,code,' . $unit->id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $unit->update($data);
        $this->logActivity('update', 'units', "Mengubah unit kerja {$unit->name}");

        return back()->with('success', 'Unit kerja berhasil diperbarui.');
    }
}
