<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use EloquentFilter\Filterable;
use ReflectionClass;
use Illuminate\Http\Resources\Json\ResourceCollection;


class ApplicationController extends BasicCrudController
{
    private $rules = [
        'user_id' => 'required|integer',
        'data' => 'required|array',
    ];

    /**
     * @OA\Get(
     *     path="/api/applications",
     *     summary="Get list of applications",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Application"))
     *     )
     * )
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));

        $query = $this->queryBuilder();

        if ($hasFilter) {
            $query = $query->filter($request->all());
        }
        $query->where('user_id', $userId);
        $data = $query->orderBy('id', 'desc')->paginate(1);

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

    /**
     * @OA\Post(
     *     path="/api/applications",
     *     summary="Create a new application",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Application created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $userId = $request->user()->id;

        // Verifica se o usuário já tem uma inscrição
        $existingApplication = Application::where('user_id', $userId)->exists();

        if ($existingApplication) {
            return response()->json([
                'message' => 'Você já possui uma inscrição registrada.'
            ], 400);
        }

        $request->merge(['user_id' => $userId]);

        return parent::store($request);
    }

    /**
     * @OA\Get(
     *     path="/api/applications/{id}",
     *     summary="Get an application by ID",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found or not authorized"
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/applications/{id}",
     *     summary="Update an application",
     *     tags={"Application"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Application")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Application not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
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
