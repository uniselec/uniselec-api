<?php

namespace App\Models;

use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use EloquentFilter\Filterable;
use Illuminate\Support\Facades\URL;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Filterable, CanResetPassword;

    protected $guard = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function sendEmailVerificationNotification()
    {
        if (empty($this->id) || empty($this->getEmailForVerification())) {
            return;
        }

        $apiUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->id,
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );

        $frontendUrl = config('app.frontend_url') . '/verify-email';

        $queryParams = parse_url($apiUrl, PHP_URL_QUERY);
        parse_str($queryParams, $parsedParams);

        $finalUrl = $frontendUrl . '?' . http_build_query([
            'id' => $this->id,
            'hash' => sha1($this->getEmailForVerification()),
            'expires' => $parsedParams['expires'] ?? null,
            'signature' => $parsedParams['signature'] ?? null,
        ]);

        $this->notify(new VerifyEmailNotification($finalUrl));
    }

    public function sendPasswordResetNotification($token)
    {
        $frontendUrl = config('app.backoffice_url');
        $resetLink = "{$frontendUrl}/reset-password/{$token}/" . urlencode($this->email);
        $this->notify(new AdminResetPasswordNotification($resetLink));
    }

}
