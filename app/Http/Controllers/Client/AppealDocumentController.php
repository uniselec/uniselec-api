<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppealDocumentResource;
use App\Models\Appeal;
use App\Models\AppealDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppealDocumentController extends Controller
{

    public function store(Request $request, Appeal $appeal)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10 MB
        ]);

        $file = $request->file('file');
        $path = $file->store('appeals', 'public');

        // Delete previous document if one already exists
        if ($appeal->documents()->exists()) {
            $oldDocument = $appeal->documents()->first();

            if ($oldDocument->path && Storage::disk('public')->exists($oldDocument->path)) {
                Storage::disk('public')->delete($oldDocument->path);
            }

            $oldDocument->delete();
        }

        $document = $appeal->documents()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return new AppealDocumentResource($document);
    }

    public function destroy(Appeal $appeal, AppealDocument $document)
    {
        // Ensure the document belongs to the given appeal
        if ($document->appeal_id !== $appeal->id) {
            return response()->json([
                'message' => 'Este documento não pertence ao recurso especificado.'
            ], 403);
        }

        if ($document->path && Storage::disk('public')->exists($document->path)) {
            Storage::disk('public')->delete($document->path);
        }

        $document->delete();

        return response()->noContent();
    }
}
