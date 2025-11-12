<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\AppealResource;
use App\Models\Appeal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppealController extends BasicCrudController
{
    private $rules = [];

    public function index(Request $request)
    {
        return parent::index($request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'application_id' => 'required|exists:applications,id',
            'justification' => 'required|string',
            'file' => 'nullable|file|max:10240', // max 10MB
        ]);

        try {
            DB::beginTransaction();

            $appeal = Appeal::create([
                'application_id' => $request->application_id,
                'justification' => $request->justification,
                'status' => 'submitted',
            ]);
    
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('appeal_documents', 'public');
    
                $appeal->documents()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao cadastrar o recurso.'
            ], 500);
        }

        // Ensure documents are loaded
        $appeal->load('documents');

        return new AppealResource($appeal);
    }

    public function update(Request $request, $id)
    {
        $appeal = Appeal::findOrFail($id);

        $request->validate([
            'application_id' => 'required|integer',
            'justification' => 'required|string',
        ]);

        $appeal->update($request->only(['application_id', 'justification']));

        // Ensure documents are loaded
        $appeal->load('documents');

        return new AppealResource($appeal);
    }

    public function show($id)
    {
        $appeal = Appeal::findOrFail($id);
        $appeal->load('documents');
        return new AppealResource($appeal);
    }

    public function destroy($id)
    {
        $appeal = Appeal::findOrFail($id);
        $appeal->load('documents');

        try {
            DB::beginTransaction();

            $appeal->delete();

            // Delete all related documents from storage and database
            foreach ($appeal->documents as $document) {

                $path = $document->path;
                $document->delete();

                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao excluir o recurso.'
            ], 500);
        }

        return response()->noContent();
    }

    protected function model()
    {
        return Appeal::class;
    }

    protected function rulesStore()
    {
        return $this->rules;
    }

    protected function rulesUpdate()
    {
        return $this->rules;
    }

    protected function resourceCollection()
    {
        return $this->resource();
    }

    protected function resource()
    {
        return AppealResource::class;
    }

}
