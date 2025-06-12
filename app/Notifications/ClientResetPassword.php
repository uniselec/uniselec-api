<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientResetPassword extends Notification
{
    use Queueable;
    private $token;
    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
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
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url') . '/reset-password';

        $resetUrl = "{$frontendUrl}/{$this->token}/{$notifiable->getEmailForPasswordReset()}";

        return (new MailMessage)
            ->subject('Recuperação de Senha')
            ->line('Você está recebendo este e-mail porque recebemos uma solicitação de recuperação de senha para sua conta.')
            ->action('Recuperar Senha', $resetUrl)
            ->line('Se você não solicitou a recuperação de senha, nenhuma ação adicional é necessária.');
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
