<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationCallerService;
use App\Services\ApplicationResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConvocationListApplicationResponseController extends Controller
{
    private ApplicationCallerService   $caller;
    private ApplicationResponseService $responser;

    public function __construct(
        ApplicationCallerService $caller,
        ApplicationResponseService $responser
    ) {
        $this->caller    = $caller;
        $this->responser = $responser;
    }

    /** POST /convocation_list_applications/{id}/call */
    public function call(int $id): JsonResponse
    {
        $this->caller->callByApplication($id);
        return response()->json(['message' => 'Convocado com sucesso']);
    }

    /** POST /convocation_list_applications/{id}/accept */
    public function accept(int $id): JsonResponse
    {
        $this->responser->accept($id);
        return response()->json(['message' => 'Aceitação registrada']);
    }

    /** POST /convocation_list_applications/{id}/decline */
    public function decline(int $id): JsonResponse
    {
        $this->responser->decline($id);
        return response()->json(['message' => 'Recusa registrada']);
    }

    /** POST /convocation_list_applications/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $this->responser->reject($id);
        return response()->json(['message' => 'Indeferimento registrado']);
    }
}
