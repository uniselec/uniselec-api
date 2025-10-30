<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ConvocationListResource;
use App\Models\ConvocationList;
use App\Models\ProcessSelection;
use App\Services\ApplicationGeneratorService;
use App\Services\SeatGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use EloquentFilter\Filterable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;


class ConvocationListController extends BasicCrudController
{
    private SeatGeneratorService $seatGeneratorService;
    private ApplicationGeneratorService $applicationGeneratorService;
    public function __construct(
        ApplicationGeneratorService $applicationGeneratorService,
        SeatGeneratorService       $seatGeneratorService
    ) {
        $this->applicationGeneratorService = $applicationGeneratorService;
        $this->seatGeneratorService        = $seatGeneratorService;
    }

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

        // 1) Bloqueia múltiplos draft
        $ps = ProcessSelection::findOrFail($request->input('process_selection_id'));
        if ($ps->convocationLists()->where('status', 'draft')->exists()) {
            return response()->json([
                'message' => 'Já existe uma lista em rascunho para este processo. '
                    . 'Publique ou descarte antes de criar outra.'
            ], 422);
        }

        // 2) Valida e cria lista + aplicações
        $data = Validator::make($request->all(), $this->rulesStore())->validate();
        $data['generated_by'] = $request->user()->id;

        [$list, $createdApps] = DB::transaction(function () use ($data) {
            $list = ConvocationList::create($data)->refresh();
            $createdApps = $this->applicationGeneratorService->generate($list);
            return [$list, $createdApps];
        });

        // 3) Gera vagas automaticamente
        $createdSeats = $this->seatGeneratorService->generateFromProcessSelection($list);

        // 4) Retorno
        $resource = $this->resource();
        return (new $resource($list))
            ->additional([
                'message' => 'Lista criada, aplicações e vagas geradas com sucesso.',
                'created_applications' => $createdApps,
                'created_seats'        => $createdSeats,
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
