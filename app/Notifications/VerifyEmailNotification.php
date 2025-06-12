<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Exemplo de como pegar o nome do usuário (se quiser incluir no e-mail)
        $userName = $notifiable->name ?? '';

        return (new MailMessage)
            ->subject('Solicitação de Filiação')
            ->greeting("Olá" . ($userName ? ", {$userName}!" : "!"))
            ->line('Seu pedido de filiação foi recebido com sucesso.')
            ->line('Em breve entraremos em contato para continuar o processo.')
            ->salutation('Atenciosamente, Equipe Sindiperitos Ceará');
    }

    protected function verificationUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());
        $expires = now()->addMinutes(config('auth.verification.expire', 60))->timestamp;

        // Gerar assinatura apenas com os parâmetros relevantes
        $signature = hash_hmac('sha256', "{$id}|{$hash}|{$expires}", config('app.key'));

        // Construir a query string

        $queryString = http_build_query([
            'id' => $id,
            'hash' => $hash,
            'expires' => $expires,
            'signature' => $signature,
        ]);

        return "{$frontendUrl}/verify-email?{$queryString}";
    }
}
