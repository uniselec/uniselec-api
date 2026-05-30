<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use App\Http\Resources\ConvocationListResource;
use App\Models\ConvocationList;
use App\Models\ProcessSelection;
use App\Services\ApplicationGeneratorService;
use App\Services\ConvocationListService;
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
    private ConvocationListService $convocationListService;

    public function __construct(
        ApplicationGeneratorService $applicationGeneratorService,
        SeatGeneratorService        $seatGeneratorService,
        ConvocationListService      $convocationListService
    ) {
        $this->applicationGeneratorService = $applicationGeneratorService;
        $this->seatGeneratorService        = $seatGeneratorService;
        $this->convocationListService      = $convocationListService;
    }

    private $rules = [
        'process_selection_id' => 'required|exists:process_selections,id',
        'name'                 => 'required|string|max:255',
        'status'               => 'nullable|in:draft,published,finalized',
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

        $latestConvocationList = $ps->convocationLists()->latest()->first();
        if($latestConvocationList && $latestConvocationList->status !== 'finalized') {
            return response()->json([
                'message' => 'Finalize a última lista criada para consguir criar uma nova'
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

    public function update(Request $request, $id)
    {
        $obj = $this->findOrFail($id);

        $rules = $request->isMethod('PUT') ? $this->rulesUpdate() : $this->rulesPatch();

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        if (isset($validatedData['status']) && $validatedData['status'] !== $obj->status) {
            $result = $this->convocationListService->validateStatusTransition($obj, $validatedData['status']);
            if ($result->isFailure()) {
                return response()->json(['message' => $result->getMessage()], 422);
            }
        }

        $obj->update($validatedData);
        $resource = $this->resource();
        return new $resource($obj);
    }


    public function destroy($id)
    {
        $list = $this->findOrFail($id);

        if ($list->status !== 'draft') {
            return response()->json([
                'message' => 'Somente listas com status rascunho podem ser excluídas.',
            ], 422);
        }

        $list->delete();

        return response()->noContent();
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
