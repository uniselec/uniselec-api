<?php

namespace App\Services;

use App\Http\Resources\ApplicationOutcomeResource;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\ApplicationResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Application;

class ApplicationService
{
    public function resolveInconsistencies(Request $request, int $id)
    {
        $rules = [
            'name_source'      => 'nullable|in:enem,application',
            'birthdate_source' => 'nullable|in:enem,application',
            'cpf_source'       => 'nullable|in:enem,application',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $sources = $request->only(['name_source', 'birthdate_source', 'cpf_source']);
        $application = Application::findOrFail($id);

        try {           
            $application->update($sources);
        } catch (\Exception $e) {
            return ServiceResult::withStatus('failure', [], 'Erro interno ao resolver inconsistências', 500);
        }

        return ServiceResult::withStatus('success', ['application' => new ApplicationResource($application->fresh()), 'applicationOutcome' => new ApplicationOutcomeResource($application->applicationOutcome)], 'Pendências resolvidas com sucesso', 200);
    }
}
