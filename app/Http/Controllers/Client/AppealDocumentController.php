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
    public function show(Appeal $appeal, AppealDocument $appealDocument)
    {
        if ($appealDocument->appeal_id !== $appeal->id) {
            return response()->json([
                'message' => 'Este documento não pertence ao recurso especificado.'
            ], 403);
        }

        return new AppealDocumentResource($appealDocument);
    }

    public function store(Request $request, Appeal $appeal)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ],[
            'file.required' => 'É obrigatório o envio de um arquivo PDF'
        ]);

        if (in_array($appeal->status, ['accepted', 'rejected'])) {
            return response()->json([
                'message' => 'O recurso já foi analisado. Alterações não são mais permitidas'
            ], 403);
        }

        $file = $request->file('file');

        $path = $file->store('appeal_documents', 'local');

        if ($appeal->documents()->exists()) {
            $oldDocument = $appeal->documents()->first();

            if ($oldDocument->path && Storage::disk('local')->exists($oldDocument->path)) {
                Storage::disk('local')->delete($oldDocument->path);
            }

            $oldDocument->delete();
        }

        $document = $appeal->documents()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return new AppealDocumentResource($document);
    }

    public function destroy(Appeal $appeal, AppealDocument $appealDocument)
    {
        if ($appealDocument->appeal_id !== $appeal->id) {
            return response()->json([
                'message' => 'Este documento não pertence ao recurso informado'
            ], 403);
        }

        if ($appealDocument->path && Storage::disk('local')->exists($appealDocument->path)) {
            Storage::disk('local')->delete($appealDocument->path);
        }

        $appealDocument->delete();

        return response()->noContent();
    }

    public function download(Appeal $appeal, AppealDocument $appealDocument)
    {
        if ($appealDocument->appeal_id !== $appeal->id) {
            return response()->json(['message' => 'Este documento não pertence ao recurso informado'], 403);
        }

        if (!Storage::disk('local')->exists($appealDocument->path)) {
            return response()->json(['message' => 'O arquivo não foi encontrado'], 404);
        }

        return Storage::disk('local')->download(
            $appealDocument->path,
            $appealDocument->original_name
        );
    }
}
