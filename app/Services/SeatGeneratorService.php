<?php

namespace App\Services;

use App\Models\{
    ConvocationList,
    ConvocationListSeat,
    Course,
    AdmissionCategory
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeatGeneratorService
{
    public function generateFromProcessSelection(ConvocationList $list): int
    {
        $ps = $list->processSelection;
        // Pega última lista published
        $last = ConvocationList::where('process_selection_id', $ps->id)
            ->where('status', 'published')
            ->latest('published_at')
            ->first();

        $categories = AdmissionCategory::pluck('id', 'name');
        $created = 0;

        DB::transaction(function () use ($list, $ps, $last, $categories, &$created) {
            // percorre cada bloco de curso no JSON de courses
            foreach ($ps->courses as $block) {
                $courseId  = $block['id'];
                $vacByCat  = $block['vacanciesByCategory'] ?? [];

                foreach ($vacByCat as $catName => $total) {
                    $catId = $categories[$catName] ?? null;
                    if (!$catId || $total <= 0) {
                        continue;
                    }

                    // já preenchidas na última published
                    $filled = $last
                        ? $last->seats()
                        ->where('course_id', $courseId)
                        ->where('origin_admission_category_id', $catId)
                        ->where('status', 'filled')
                        ->count()
                        : 0;

                    $toCreate = max(0, $total - $filled);

                    for ($i = 1; $i <= $toCreate; $i++) {
                        ConvocationListSeat::create([
                            'convocation_list_id'          => $list->id,
                            'course_id'                    => $courseId,
                            'origin_admission_category_id' => $catId,
                            'current_admission_category_id' => $catId,
                            'status'                       => 'open',
                            // seat_code será gerado no booted()
                        ]);
                        $created++;
                    }
                }
            }
        });

        return $created;
    }
}
