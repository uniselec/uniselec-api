<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ApplicationStatusResource;
use App\Models\ApplicationStatus;
use Illuminate\Http\Request;

class ApplicationStatusController extends BasicCrudController
{
    private $rules = [
        'enem' => 'required|max:255',
        "application_id" => 'required',
        "scores" => 'required|array',  // Confirme que scores é um array
        "original_scores" => 'required|string'
    ];

    public function index(Request $request)
    {
        return parent::index($request);
    }
    public function store(Request $request)
    {
        $data = $this->validate($request, $this->rulesStore());

        if (is_array($data['scores'])) {
            $data['scores'] = json_encode($data['scores']);
        }

        $enemScore = $this->model()::create($data);

        $enemScore->refresh();
        $resource = $this->resource();
        return new $resource($enemScore);
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
        return ApplicationStatus::class;
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
        return ApplicationStatusResource::class;
    }
}
