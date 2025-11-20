<?php

namespace App\Services;

use App\Notifications\ApplicationStatusNotification;
use Illuminate\Support\Facades\Log;
use App\DTOs\ApplicationStatusDTO;
use App\Models\ProcessSelection;

class NotifyApplicationsByStatusService
{
	/**
	 * Runs the notification process for given statuses.
	 *
	 * @param ProcessSelection $processSelection
	 * @param string[] $statusForNotification  List of statuses that should receive a notification
	 * @return ServiceResult
	 */
	public function run(ProcessSelection $processSelection, array $statusForNotification) : ServiceResult
	{
		try {
			$applications = $processSelection->applications()
				->whereHas('applicationOutcome', function ($query) use ($statusForNotification) {
					$query->whereIn('status', $statusForNotification);
				})->get();

			if ($applications->isEmpty()) {
				return ServiceResult::withStatus(
					'failure',
					[],
					"Ops! Nenhuma aplicação corresponde aos status selecionados.",
					'404'
				);
			}

			$notifiedCount = 0;
			$notifiedApplicationIds = [];

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

				$notifiedCount++;
				$notifiedApplicationIds[] = $application->id;
			}

			return ServiceResult::success(
				[
					'notifications_sent_count' => $notifiedCount,
					'notified_application_ids' => $notifiedApplicationIds,
				],
				"Notificações enviadas com sucesso. Total: {$notifiedCount}"
			);

		} catch (\Exception $e) {
			Log::error($e->getMessage(), [
				'exception' => get_class($e),
				'file'      => $e->getFile(),
				'line'      => $e->getLine(),
				'trace'     => $e->getTraceAsString(),
				'user_id'   => auth()->id() ?? null,
			]);
			return ServiceResult::withStatus('failure', [], $e->getMessage(), 500);
		}
	}
}

