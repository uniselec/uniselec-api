<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ProcessSelectionResource;
use App\Models\ProcessSelection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;


class ProcessSelectionController extends BasicCrudController
{

    private $rules = [
        'status' => "",
        'name' => "",
        'description' => "",
        'start_date' => "",
        'end_date' => "",
        'type' => "",
        'courses' => "",
        'admission_categories' => "",
        'knowledge_areas' => "",
        'allowed_enem_years' => "",
        'bonus_options' => "",
        'currenty_step' => "",
        'remap_rules'          => 'nullable|array',
    ];

    public function show($id)
    {
        $processSelection = $this->queryBuilder()
            ->with(['documents'])
            ->findOrFail($id);

        return new ProcessSelectionResource($processSelection);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));
        $query = $this->queryBuilder()->with(['documents']);
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
    private function buildDefaultRules(array $categoryNames): array
    {
        $rules = [];
        foreach ($categoryNames as $name) {
            $rules[$name] = array_values(
                array_filter($categoryNames, fn($n) => $n !== $name)
            );
        }
        return $rules;
    }
    public function store(Request $request)
    {
        $data = Validator::make($request->all(), $this->rulesStore())
            ->validate();
        if (empty($data['remap_rules']) && !empty($data['admission_categories'])) {
            $names = collect($data['admission_categories'])
                ->pluck('name')
                ->all();
            $data['remap_rules'] = $this->buildDefaultRules($names);
        }


        $process = ProcessSelection::create($data)->refresh();
        return new ProcessSelectionResource($process);
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
