<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\AppealResource;
use App\Models\Appeal;
use Illuminate\Http\Request;

class AppealController extends BasicCrudController
{
    private $rules = [];

    public function index(Request $request)
    {
        return parent::index($request);
    }

    public function show($id)
    {
        $appeal = Appeal::findOrFail($id);
        $appeal->load('documents');
        return new AppealResource($appeal);
    }

    public function store(Request $request)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $appeal = Appeal::findOrFail($id);

        $request->validate([
            'decision' => 'string|nullable',
            'reviewed_by' => 'required|string',
            'status' => 'required|string|in:accepted,rejected',
        ]);

        $appeal->update($request->only(['decision', 'reviewed_by', 'status']));

        $appeal->load('documents');

        return new AppealResource($appeal);
    }

    public function destroy($id)
    {
        //
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
