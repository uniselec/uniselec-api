<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\BasicCrudController;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class DocumentController extends BasicCrudController
{
    private $rules = [
        'title' => 'required|max:255',
        'description' => 'required|max:255'
    ];


    public function index(Request $request)
    {
        return parent::index($request);
    }

    protected function queryBuilder(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\Document::query()->where('status', 'published');
    }

    public function show($id)
    {
        return parent::show($id);
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
