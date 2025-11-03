<?php

namespace App\Http\Controllers\Admin;

use App\Services\NotifyApplicationsByStatusService;
use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use Illuminate\Http\Request;

class ProcessSelectionNotifyController extends Controller
{
    public function __construct(private NotifyApplicationsByStatusService $sevice) 
    {
           
    }

    public function notifyByStatus(Request $request, int $selectionId)
    {
        $statusForNotification = $request->query('status');
        if (!$statusForNotification) {
            return response()->json([
                'error' => 'Não há status selecionado para envio de notificação.'
            ], 400);
        }

        $processSelection = ProcessSelection::find($selectionId);
        if (!$processSelection) {
            return response()->json([
                'error' => 'O processo de seleção informado não existe.'
            ], 404);
        }

        $resultNotifications = $this->sevice->run($processSelection, $statusForNotification);
        if ($resultNotifications->isFailure()) {
            return response()->json([
                'error' => $resultNotifications->getMessage()
            ], $resultNotifications->getCode() ?: 500);
        }

        return response()->json([
            'message' => $resultNotifications->getMessage(),
            'data'    => $resultNotifications->getData(),
        ]);
    }
}
