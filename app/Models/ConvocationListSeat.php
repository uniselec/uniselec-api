<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use EloquentFilter\Filterable;

class ConvocationListSeat extends Model
{
    use Filterable;
    protected $fillable = [
        'convocation_list_id',
        'seat_code',
        'course_id',
        'origin_admission_category_id',
        'current_admission_category_id',
        'status',
        'application_id',
    ];

    /* ─────────────── Geração automática do seat_code ─────────────── */
    protected static function booted(): void
    {
        static::creating(function (self $seat) {
            if (!$seat->seat_code) {
                $course    = Course::find($seat->course_id);
                $category  = AdmissionCategory::find($seat->origin_admission_category_id);
                $list      = ConvocationList::find($seat->convocation_list_id);

                $seq = self::where('convocation_list_id', $seat->convocation_list_id)
                    ->where('course_id', $seat->course_id)
                    ->where('origin_admission_category_id', $seat->origin_admission_category_id)
                    ->count() + 1;

                $seat->seat_code = self::makeSeatCode(
                    $list->process_selection_id,
                    $list->id,
                    $course->name,
                    $category->name,
                    $seq
                );
            }
        });
    }

    /** Gera código: <PSID>-<CURSO>-<CAT>-<SEQ> */
    public static function makeSeatCode(
        int $processSelectionId,
        int $convocationListId,
        string $courseName,
        string $categoryName,
        int $seq
    ): string {
        $ps   = str_pad($processSelectionId, 3, '0', STR_PAD_LEFT);   // 001
        $list = str_pad($convocationListId,    3, '0', STR_PAD_LEFT); // 007
        $cur  = strtoupper(Str::substr(Str::slug(Str::words($courseName, 1, ''), ''), 0, 3)); // MED
        $cat  = preg_replace('/\s+/', '', Str::ascii($categoryName)); // LB-PPI
        $seq3 = str_pad($seq, 3, '0', STR_PAD_LEFT);                  // 012

        return "{$ps}-{$list}-{$cur}-{$cat}-{$seq3}";
    }


    public function list(): BelongsTo
    {
        return $this->belongsTo(ConvocationList::class, 'convocation_list_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function originCategory(): BelongsTo
    {
        return $this->belongsTo(AdmissionCategory::class, 'origin_admission_category_id');
    }

    public function currentCategory(): BelongsTo
    {
        return $this->belongsTo(AdmissionCategory::class, 'current_admission_category_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
