<?php

namespace App\Http\Controllers;

use App\Models\ArchiveType;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class ArchiveTypeController extends Controller
{
    use LogsActivity;

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:archive_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        ArchiveType::create($data);
        $this->logActivity('create', 'archive_types', "Menambah jenis dokumen {$data['name']}");

        return back()->with('success', 'Jenis dokumen ditambahkan.');
    }
}
