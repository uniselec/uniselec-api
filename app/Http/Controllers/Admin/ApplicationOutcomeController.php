<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ApplicationOutcomeResource;
use App\Models\ApplicationOutcome;
use Illuminate\Http\Request;

class ApplicationOutcomeController extends BasicCrudController
{
    private $rules = [
            "application_id" => 'required',
            "status" => 'required',
            "classification_status" => 'required',
            "average_score" => 'required',
            "final_score" => 'required',
            "ranking" => 'required',
            "reason" => 'required',
    ];

    public function index(Request $request)
    {
        return parent::index($request);
    }
    public function store(Request $request)
    {
        $data = $this->validate($request, $this->rulesStore());


        if (empty($data['classification_status'])) {
            return response()->json(['error' => 'O campo classification_status é obrigatório.'], 422);
        }

        $applicationOutcome = $this->model()::create($data);

        $applicationOutcome->refresh();
        $resource = $this->resource();
        return new $resource($applicationOutcome);
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
}
