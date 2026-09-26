<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class ArchiveCategoryController extends Controller
{
    use LogsActivity;

    public function index()
    {
        $categories = ArchiveCategory::withCount('archives')->with('types')->get();
        return view('archives.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:archive_categories,code'],
            'description' => ['nullable', 'string'],
        ]);

        ArchiveCategory::create($data);
        $this->logActivity('create', 'categories', "Menambah kategori {$data['name']}");

        return back()->with('success', 'Kategori arsip ditambahkan.');
    }
}
