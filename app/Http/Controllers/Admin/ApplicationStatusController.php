<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ApplicationStatusController extends BasicCrudController
{
    private $rules = [
        'application_id' => 'required'
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
        return Document::class;
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
        return DocumentResource::class;
    }
}
