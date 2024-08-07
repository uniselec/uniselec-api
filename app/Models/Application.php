<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="Application",
 *     required={"user_id", "data"},
 *     @OA\Property(property="id", type="integer", readOnly=true, description="Application ID"),
 *     @OA\Property(property="user_id", type="integer", description="User ID who made the application"),
 *     @OA\Property(property="data", type="object", description="Application data in JSON format"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class Application extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'user_id',
        'data',
        'verification_code'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the user that owns the application.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
