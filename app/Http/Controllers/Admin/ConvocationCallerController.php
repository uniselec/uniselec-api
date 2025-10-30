<?php
// app/Http/Controllers/Admin/ConvocationCallerController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationCallerService;
use Illuminate\Http\JsonResponse;

class ConvocationCallerController extends Controller
{
    private ApplicationCallerService $caller;

    public function __construct(ApplicationCallerService $caller)
    {
        $this->caller = $caller;
    }

    /**
     * POST /admin/super_user/convocation_list_applications/{cla}/call
     * @param  int  $claId
     */
    public function __invoke(int $claId): JsonResponse
    {
        $this->caller->callByApplication($claId);

        return response()->json([
            'message' => 'Convocação processada com sucesso.',
        ]);
    }
}
