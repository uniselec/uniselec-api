<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\EnemScoreResource;
use App\Models\EnemScore;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
class EnemScoreController extends BasicCrudController
{
    private $rules = [
        'enem' => 'required|max:255',
        "scores" => 'required',
        "original_scores" => 'required'
    ];

    public function index(Request $request)
    {
        return parent::index($request);
    }
    public function queryBuilder(): Builder
    {
        return parent::queryBuilder()->with('application');
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rulesStore());
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $applications = Application::where('data->enem', $request->enem)->get();

        if ($applications->isEmpty()) {
            return response()->json(['error' => 'Nenhuma inscrição encontrada para o ENEM informado.'], 404);
        }

        $enemScores = [];
        foreach ($applications as $application) {
            $enemScore = EnemScore::updateOrCreate(
                [
                    'enem' => $request->enem,
                    'application_id' => $application->id,
                ],
                [
                    'scores' => $request->scores,
                    'original_scores' => $request->original_scores,
                ]
            );

            $enemScores[] = new EnemScoreResource($enemScore);
        }

        return response()->json($enemScores, 201);
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
        return EnemScore::class;
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
        return EnemScoreResource::class;
    }
}
