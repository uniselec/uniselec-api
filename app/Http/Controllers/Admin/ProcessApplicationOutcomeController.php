<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProcessApplicationOutcome;

class ProcessApplicationOutcomeController extends Controller
{
    public function processOutcomes()
    {
        $service = new ProcessApplicationOutcome();
        $service->process();
        return response()->json(['message' => 'Application outcomes processed successfully.']);
    }
}
