<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\EnemScoreResource;
use App\Models\EnemScore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use ReflectionClass;
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
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));
        $query = $this->queryBuilder();
        if ($hasFilter) {
            $query = $query->filter($request->all());
        }
        $query->whereNotNull('created_at');
        $data = $request->has('all') || ! $this->defaultPerPage
            ? $query->get()
            : $query->paginate($perPage);
        $resourceCollectionClass = $this->resourceCollection();
        $refClass = new ReflectionClass($this->resourceCollection());
        return $refClass->isSubclassOf(ResourceCollection::class)
            ? new $resourceCollectionClass($data)
            : $resourceCollectionClass::collection($data);
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

    public function queryBuilder(): Builder
    {
        return parent::queryBuilder()->with('application');
    }
}
