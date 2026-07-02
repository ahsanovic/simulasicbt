<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuestionContentImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ], [
            'image.required' => 'Gambar wajib dipilih.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus JPG, PNG, WEBP, atau GIF.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
        ]);

        $path = $validated['image']->store('question-content', 'public');

        return response()->json([
            'url' => '/storage/'.$path,
        ]);
    }
}
