<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ConvocationListSeatResource;
use App\Models\ConvocationListSeat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use ReflectionClass;
use Illuminate\Database\Eloquent\Builder;

class ConvocationListSeatController extends BasicCrudController
{

    private $rules = [
        'convocation_list_id' => '',
        'seat_code' => '',
        'course_id' => '',
        'origin_admission_category_id' => '',
        'current_admission_category_id' => '',
        'status' => '',
        'application_id' => '',
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
        return ConvocationListSeat::class;
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
        return ConvocationListSeatResource::class;
    }

    public function queryBuilder(): Builder
    {
        return parent::queryBuilder()->with([
            'course:id,name,modality',
            'originCategory:id,name',
            'currentCategory:id,name',
            'application:id,form_data',
        ]);
    }
}
