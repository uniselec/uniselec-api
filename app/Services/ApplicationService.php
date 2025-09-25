<?php

namespace App\Services;

use App\Http\Resources\ApplicationOutcomeResource;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\ApplicationResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Enums\InconsistencyType;
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

        $application = Application::findOrFail($id);
        $applicationOutcome = $application->applicationOutcome;
        $sources = $request->only(['name_source', 'birthdate_source', 'cpf_source']);

        if($applicationOutcome->reason) {
            $reasons = array_map('trim', explode(';', $applicationOutcome->reason));
        } else {
            $reasons = [];
        }

        foreach ($sources as $field => $source) {
            if ($source && in_array($source, ['enem', 'application'])) {
               
                $enum = match ($field) {
                    'name_source'      => InconsistencyType::NAME,
                    'birthdate_source' => InconsistencyType::BIRTHDATE,
                    'cpf_source'       => InconsistencyType::CPF,
                };

                $reasonIndex = array_search($enum->value, $reasons);

                if ($reasonIndex !== false) {
                    unset($reasons[$reasonIndex]);
                }
            }
        }

        try {
            DB::beginTransaction();
            
            $application->update($sources);

            $reasonsString = empty($reasons) ? null : implode(';', array_values($reasons));
            $applicationOutcome->update(['reason' => $reasonsString]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ServiceResult::withStatus('failure', [], 'Erro interno ao resolver inconsistências', 500);
        }

        return ServiceResult::withStatus('success', ['application' => new ApplicationResource($application->fresh()), 'applicationOutcome' => new ApplicationOutcomeResource($applicationOutcome->fresh())], 'Pendências resolvidas com sucesso', 200);
    }
}
