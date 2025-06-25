<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\BasicCrudController;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\ProcessSelection;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use EloquentFilter\Filterable;
use ReflectionClass;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class ApplicationController extends BasicCrudController
{
    private $rules = [
        'user_id' => 'required|integer',
        'form_data' => 'required|array',
    ];




    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));

        $query = $this->queryBuilder()->with('processSelection');

        if ($hasFilter) {
            $query = $query->filter($request->all());
        }

        $query->where('user_id', $user->id);
        $data = $query->orderBy('id', 'desc')->paginate($perPage);

        if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return ApplicationResource::collection($data->items())->additional([
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                ],
            ]);
        }

        return ApplicationResource::collection($data);
    }


    public function store(Request $request)
    {

        $validatedData = $request->validate([
            'form_data'  => 'required',
            'process_selection_id'   => 'required',
        ]);


        $processSelection = ProcessSelection::where('id', $validatedData['process_selection_id'])
            ->where('status', 'active')
            ->firstOrFail();
        $processSelectionId = $processSelection->id;
        $start = $processSelection->start_date;
        $end = $processSelection->end_date;
        $now = now();
        if ($now->lt($start) || $now->gt($end)) {
            return response()->json([
                'message' => 'Inscrições estão fechadas. O período de inscrição é de ' . $start->format('d/m/Y H:i') . ' até ' . $end->format('d/m/Y H:i') . '.',
            ], 403);
        }

        $userId = $request->user()->id;

        $existingApplication = Application::where('user_id', $userId)
            ->where('process_Selection_id', $processSelectionId)
            ->first();


        // if($existingApplication) {
        //     return response()->json([
        //         'message' => 'Já tem uma inscrição para este candidato neste processo.'
        //     ], 422);
        // }


        // $applicationData = $validatedData['data'];
        $applicationData = $request->all();

        $applicationData['user_id'] = $userId;

        $currentTimestamp = now()->toDateTimeString();
        if (!isset($applicationData['form_data'])) {
            $applicationData['form_data'] = [];
        }

        $applicationData['form_data']['updated_at'] = $currentTimestamp;
        $applicationData['process_selection_id'] = $processSelectionId;
        $applicationData['verification_code'] = md5(json_encode($applicationData['form_data']));


        if ($existingApplication) {
            $existingApplication->update($applicationData);
            return response()->json([
                'message' => 'Inscrição atualizada com sucesso.',
                'application' => $existingApplication
            ], 200);
        }

        $request->merge(['user_id' => $userId]);
        $application = Application::create($applicationData);

        return response()->json([
            'message' => 'Inscrição criada com sucesso.',
            'application' => $application
        ], 201);
    }


    public function show($id)
    {

        $userId = request()->user()->id;
        $application = $this->model()::where('id', $id)
            ->where('user_id', $userId)
            ->first();
        if (!$application) {
            return response()->json(['message' => 'Application not found or not authorized'], 404);
        }
        return new ApplicationResource($application);
    }

    public function update(Request $request, $id)
    {
        $user        = $request->user();
        $application = Application::with('processSelection')->find($id);

        if (!$application || $application->user_id !== $user->id) {
            return response()->json(['error' => 'Você não tem permissão para atualizar esta inscrição.'], 403);
        }

        $processSelection = $application->processSelection;

        if (!$processSelection || $processSelection->status !== 'active') {
            return response()->json(['message' => 'Processo de seleção inativo ou inexistente.'], 403);
        }

        $now  = now();
        $from = $processSelection->start_date;
        $to   = $processSelection->end_date;

        if ($now->lt($from) || $now->gt($to)) {
            return response()->json([
                'message' => "Inscrições estão fechadas. O período de inscrição é de {$from->format('d/m/Y H:i')} até {$to->format('d/m/Y H:i')}.",
            ], 403);
        }

        return parent::update($request, $id);
    }

    /**
     * Método `destroy` removido conforme solicitado
     */
    public function destroy($id)
    {
        return response()->json(['error' => 'Method not allowed.'], 405);
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
        return ApplicationResource::collection($this->model()::all());
    }

    protected function resource()
    {
        return ApplicationResource::class;
    }
}
