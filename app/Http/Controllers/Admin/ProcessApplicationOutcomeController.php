<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProcessApplicationOutcome;
use App\Services\ProcessApplicationOutcomeWithoutPending;

class ProcessApplicationOutcomeController extends Controller
{
    public function processOutcomes(int $selectionId)
    {
        (new ProcessApplicationOutcome($selectionId))->process();

        return response()->json([
            'message' => "Outcomes processed for process {$selectionId}"
        ]);
    }

    public function processOutcomesWithoutPending(int $selectionId)
    {
        (new ProcessApplicationOutcomeWithoutPending($selectionId))->process();

        return response()->json([
            'message' => "Outcomes processed (no pending) for process {$selectionId}"
        ]);
    }
}
