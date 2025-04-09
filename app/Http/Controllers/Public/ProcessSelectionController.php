<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ProcessSelectionResource;
use App\Models\ProcessSelection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use ReflectionClass;

class ProcessSelectionController extends BasicCrudController
{
    private $rules = [
        'name' => '',
        'description' => '',
        'status' => '',
        'start_date' => '',
        'end_date' => '',
        'type' => '',
    ];

    public function show($id)
    {
        $processSelection = $this->queryBuilder()
            ->with([
                'documents' => function ($query) {
                    $query->where('status', 'published');
                }
            ])
            ->findOrFail($id);


        return new ProcessSelectionResource($processSelection);
    }


    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));
        $query = $this->queryBuilder();
        if ($hasFilter) {
            $query = $query->filter($request->all());
        }
        $query->where('status', 'active');
        $query->whereNotNull('created_at');
        $data = $request->has('all') || !$this->defaultPerPage
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
        return ProcessSelection::class;
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
        return ProcessSelectionResource::class;
    }
}
