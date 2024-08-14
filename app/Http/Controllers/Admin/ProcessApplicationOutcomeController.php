<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProcessApplicationOutcome;
use App\Services\ProcessApplicationOutcomeWithoutPending;

class ProcessApplicationOutcomeController extends Controller
{
    public function processOutcomes()
    {
        $service = new ProcessApplicationOutcome();
        $service->process();
        return response()->json(['message' => 'Application outcomes processed successfully.']);
    }

    public function processOutcomesWithoutPending()
    {
        $service = new ProcessApplicationOutcomeWithoutPending();
        $service->process();
        return response()->json(['message' => 'Application outcomes processed successfully without pending status.']);
    }
}
