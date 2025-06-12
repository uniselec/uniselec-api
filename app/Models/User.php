<?php

namespace App\Models;

use App\Notifications\UserResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OpenApi\Annotations as OA;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Auth\Passwords\CanResetPassword;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Filterable, CanResetPassword, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'cpf',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $guard = 'web';
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
        $frontendUrl = config('app.frontend_url');
        $resetLink = "{$frontendUrl}/reset-password/{$token}/" . urlencode($this->email);
        $this->notify(new UserResetPasswordNotification($resetLink));
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
