<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ApplicationOutcomeResource;
use App\Models\Application;
use App\Models\ApplicationOutcome;
use App\Models\EnemScore;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ApplicationOutcomeController extends BasicCrudController
{
    private $rules = [
        "application_id" => 'required',
        "status" => 'required',
        "reason" => 'required',
    ];

    public function queryBuilder(): Builder
    {
        return parent::queryBuilder()->with([
            'application',
            'application.enemScore',
        ]);
    }

    public function index(Request $request)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        return parent::index($request);
    }
    public function store(Request $request)
    {
        return response()->json([
            'error' => 'O método store não é permitido para ApplicationOutcome. Por favor, utilize a rota de processamento para criar ou atualizar ApplicationOutcomes.'
        ], 403);
    }

    public function show($id)
    {
        return parent::show($id);
    }

    public function update(Request $request, $id)
    {
        return parent::update($request, $id);
    }

    public function destroy($id)
    {
        return parent::destroy($id);
    }

    protected function model()
    {
        return ApplicationOutcome::class;
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
        return ApplicationOutcomeResource::class;
    }

    public function patchUpdate(Request $request, $id)
    {
        $applicationOutcome = ApplicationOutcome::find($id);

        if (!$applicationOutcome) {
            return response()->json(['error' => 'ApplicationOutcome not found.'], 404);
        }
        $validatedData = $request->validate([
            'status' => 'required|string|in:approved,rejected,pending',
            'reason' => 'nullable|string',
        ]);

        if ($validatedData['status'] === 'rejected' && empty($validatedData['reason'])) {
            return response()->json(['error' => 'Reason is required when status is rejected.'], 422);
        }
        $applicationOutcome->update($validatedData);
        return new ApplicationOutcomeResource($applicationOutcome);
    }
}
