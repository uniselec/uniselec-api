<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, $token) {

            $url = env('WEB_APPLICATION_LINK','https://selecoes.unilab.edu.br'). '/reset-password/' . $token . '/' . urlencode($notifiable->getEmailForPasswordReset());

            return (new MailMessage)
                ->greeting('Olá!')
                ->subject('UNILAB: Notificação de Recuperação de Senha')
                ->line('Você recebeu este e-mail porque nós recebemos uma requisição de redefinição da sua senha.')
                ->action('Recuperar Senha', $url)
                ->line('Esse link de recuperação expira em 60 minutos.')
                ->line('Se você não requisitou a recuperação de sua senha não precisa fazer nada.');
        });
    }
}
