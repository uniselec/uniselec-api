<?php

namespace App\Notifications;

use App\DTOs\ApplicationStatusDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ApplicationStatusDTO $applicationStatusDTO)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = "";
        switch ($this->applicationStatusDTO->status) {
            case 'approved':
                $status = "Situação da Inscrição: DEFERIDA.";
                if (!empty($this->applicationStatusDTO->reasons)) {
                    $message = "Mas existem as seguintes inconsistências:";
                }
                break;
            case 'pending':
                $status = "Situação da Inscrição: COM PENDÊNCIAS.";
                $message = "Existem as seguintes pendêcias em sua inscrição:";
                break;
            case 'rejected':
                $status = "Situação da Inscrição: INDEFERIDA.";
                $message = "Sua inscrição foi indeferida devido aos seguintes motivos:";
                break;
            default:
                $status = "";
                $message = "";
                break;
        }

        $baseUrl = config('app.frontend_url_client');
        $url = $baseUrl . "/process-selections/details/{$this->applicationStatusDTO->processSelectionId}";

        return (new MailMessage)
            ->subject($this->applicationStatusDTO->processSelectionName)
            ->markdown('mail.application_status', [
                'candidateName'         => $this->applicationStatusDTO->candidateName,
                'processSelectionName'  => $this->applicationStatusDTO->processSelectionName,
                'message'               => $message,
                'status'                => $status,
                'reasons'               => $this->applicationStatusDTO->reasons, 
                'url'                   => url($url),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
