<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnemScoreResource;
use App\Models\EnemScore;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EnemScoreController extends BasicCrudController
{
    private $rules = [
        'enem' => 'required|max:255',
        "application_id" => 'required',
        "enem" => 'required',
        "scores" => 'required',
        "original_scores" => 'required'
    ];

    public function index(Request $request)
    {
        return parent::index($request);
    }

    public function store(Request $request)
    {
        return parent::store($request);
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
