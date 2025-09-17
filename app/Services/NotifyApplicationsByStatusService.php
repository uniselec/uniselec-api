<?php

namespace App\Services;

use App\DTOs\ApplicationStatusDTO;
use App\Models\ProcessSelection;
use App\Notifications\ApplicationStatusNotification;
use Carbon\Carbon;

class NotifyApplicationsByStatusService
{
	public function run(ProcessSelection $processSelection) 
	{
		$applications = $processSelection->applications;

		foreach ($applications as $application) {
				$processSelectionId = $processSelection->id;
				$processSelectionName = $processSelection->name;
				$candidateName = $application->form_data['name'];
				$status = $application->applicationOutcome->status;
	
				$reasons = $application->applicationOutcome->reason ? explode(';', $application->applicationOutcome->reason) : [];
				
				$applicationStatusDTO = new ApplicationStatusDTO(
					$processSelectionId,
					$processSelectionName,
					$candidateName,
					$status,
					$reasons
				);

				$application->user->notify(new ApplicationStatusNotification($applicationStatusDTO));
		}
	}
}

