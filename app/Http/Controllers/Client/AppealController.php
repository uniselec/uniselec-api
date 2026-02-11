<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\AppealResource;
use App\Models\Appeal;
use App\Models\Application;
use App\Models\ProcessSelection;
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
            'justification' => 'required|string',
            'application_id' => 'required|exists:applications,id',
        ],[
            'justification.required' => 'A justificativa é obrigatória',
            'application_id.required' => 'O ID da inscrição é obrigatório'
        ]);

        $application = Application::find($request->application_id);
        if (!$application) {
            return response()->json([
                'message' => 'Não foi possível encontrar a inscrição'
            ], 403);
        }

        $process = $application->processSelection;
        if (!$process) {
            return response()->json([
                'message' => 'Processo seletivo não encontrado.'
            ], 403);
        }

        // Start and end dates of the appeals period
        $appeal_start_date = $process->appeal_start_date;
        $appeal_end_date   = $process->appeal_end_date;

        $now = now();

        // Check if it is within the allowed period
        if (!($now->greaterThanOrEqualTo($appeal_start_date) && $now->lessThanOrEqualTo($appeal_end_date))) {
            return response()->json([
                'message' => 'O período para interpor recurso não está disponível no momento'
            ], 403);
        }

        $appeal = Appeal::create([
            'application_id' => $request->application_id,
            'justification' => $request->justification,
            'status' => 'submitted',
        ]);
    
        $appeal->load('documents');

        return new AppealResource($appeal);
    }

    public function update(Request $request, $id)
    {
        $appeal = Appeal::findOrFail($id);

        $request->validate([
            'justification' => 'required|string',
            'application_id' => 'required|exists:applications,id',
        ],[
            'justification.required' => 'A justificativa é obrigatória.',
            'application_id.required' => 'O id da aplicação é obrigatório.'
        ]);

        if (in_array($appeal->status, ['accepted', 'rejected'])) {
            return response()->json([
                'message' => 'O recurso já foi analisado. Alterações não são mais permitidas'
            ], 403);
        }

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
                'message' => 'Ocorreu um erro ao excluir o recurso'
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
