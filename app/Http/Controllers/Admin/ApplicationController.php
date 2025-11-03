<?php

namespace App\Http\Controllers\Admin;


use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ApplicationResource;
use Illuminate\Support\Facades\Validator;
use App\Services\ApplicationService;
use EloquentFilter\Filterable;
use Illuminate\Http\Request;
use App\Models\Application;
use ReflectionClass;

class ApplicationController extends BasicCrudController
{

    private $rules = [
        'title' => 'required',
        'body' => 'required',
    ];

    public function __construct(private ApplicationService $applicationService)
    {
    }

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

    public function show($id)
    {
        $application = Application::findOrFail($id);
        return new ApplicationResource($application);
    }

    public function resolveInconsistencies(Request $request, $id)
    {
        try {
            $result = $this->applicationService->resolveInconsistencies($request, $id);

            if ($result->getStatus() === 'failure') {
                return response()->json([
                    'message' => $result->getMessage()
                ], $result->getCode());
            }

            return $result->getData();

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    protected function model()
    {
        return Application::class;
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rulesStore());
        $validatedData = $validator->validate();
        $validatedData['admin_id'] = request()->input('admin_id', request()->user()->id);

        $obj = $this->queryBuilder()->create($validatedData);
        $obj->refresh();
        $resource = $this->resource();
        return new $resource($obj);
    }
    protected function resource()
    {
        return ApplicationResource::class;
    }
}
