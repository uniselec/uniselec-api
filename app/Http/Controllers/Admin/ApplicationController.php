<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasicCrudController;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
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
        'data' => 'required|array',
    ];


    public function changeAdminPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $admin = $request->user();


        if (!Hash::check($request->current_password, $admin->password)) {
            return response()->json(['error' => 'Senha atual incorreta.'], 403);
        }
        $admin->password = Hash::make($request->new_password);
        $admin->save();

        return response()->json(['message' => 'Senha alterada com sucesso.'], 200);
    }
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', $this->defaultPerPage);
        $hasFilter = in_array(Filterable::class, class_uses($this->model()));

        $query = $this->queryBuilder()->with('user');

        if ($hasFilter) {
            $query = $query->filter($request->all());
        }

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
        $application = $this->model()::with('user')
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json(['message' => 'Application not found or not authorized'], 404);
        }
        return new ApplicationResource($application);
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
