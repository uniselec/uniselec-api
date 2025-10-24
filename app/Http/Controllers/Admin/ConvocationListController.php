<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ConvocationListResource;
use App\Models\ConvocationList;
use App\Models\ProcessSelection;
use App\Services\ApplicationGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;


class ConvocationListController extends BasicCrudController
{
    public function __construct(
        private ApplicationGeneratorService $applicationGeneratorService
    ) {}

    private $rules = [
        'process_selection_id' => 'required|exists:process_selections,id',
        'name'                 => 'required|string|max:255',
        'status'               => 'nullable|in:draft,published',
        'published_at'         => 'nullable|date',

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

    /* ------------------------------------------------------------------ */
    public function store(Request $request) // <-- assinatura mantida
    {
        $data = Validator::make($request->all(), $this->rulesStore())->validate();
        $data['generated_by'] = $request->user()->id;

        [$list, $created] = DB::transaction(function () use ($data) {
            /** @var ConvocationList $list */
            $list = ConvocationList::create($data)->refresh();

            // Gera as aplicações logo após criar a lista
            $created = 0;
            if (!$list->applications()->exists()) {
                $created = $this->applicationGeneratorService->generate($list);
            }

            return [$list, $created];
        });

        return (new ConvocationListResource($list))
            ->additional([
                'message' => 'Lista criada e aplicações geradas com sucesso.',
                'created' => $created,
            ]);
    }


    protected function model()
    {
        return ConvocationList::class;
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
        return ConvocationListResource::class;
    }
}
