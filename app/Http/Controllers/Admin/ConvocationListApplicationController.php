<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ConvocationListApplicationResource;
use App\Models\ConvocationListApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use ReflectionClass;
use Illuminate\Database\Eloquent\Builder;

class ConvocationListApplicationController extends BasicCrudController
{

    private $rules = [
        'convocation_list_id' => '',
        'application_id' => '',
        'course_id' => '',
        'admission_category_id' => '',
        'seat_id' => '',
        'ranking_at_generation' => '',
        'status' => '',
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
        return ConvocationListApplication::class;
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
        return ConvocationListApplicationResource::class;
    }
    public function queryBuilder(): Builder
    {
        return parent::queryBuilder()->with([
            'application:id,form_data',
            'course:id,name,modality',
            'category:id,name',
            'seat:id,seat_code,status',
        ]);
    }
}
