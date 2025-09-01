<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use App\Services\NotifyApplicationsByStatusService;

class ProcessSelectionNotifyController extends Controller
{
    public function __construct(private NotifyApplicationsByStatusService $sevice) 
    {
           
    }

    public function notifyByStatus(int $selection) {

        $processSelection = ProcessSelection::find($selection);

        if ($processSelection) {
            $this->sevice->run($processSelection);
        } else {
            return response()->json(['error' => 'O processo de seleção informado não existe.']);
        }

        return response()->json(['message' => 'Notificações enviadas com sucesso.']);
    }
}
