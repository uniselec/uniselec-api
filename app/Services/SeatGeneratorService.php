<?php

namespace App\Services;

use App\Models\{
    ConvocationList, ConvocationListSeat, Course, AdmissionCategory
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeatGeneratorService
{
    public function generate(ConvocationList $list, array $seatData): int
    {
        $categories     = AdmissionCategory::pluck('id', 'name'); // "AC" => 9 …
        $createdCounter = 0;

        DB::transaction(function () use ($list, $seatData, $categories, &$createdCounter) {

            foreach ($seatData as $courseBlock) {
                $course     = Course::findOrFail($courseBlock['course_id']);
                $vacancies  = $courseBlock['vacanciesByCategory'] ?? [];

                foreach ($vacancies as $catName => $qty) {
                    $catId = $categories[$catName] ?? null;
                    if (!$catId || $qty <= 0) {
                        continue;                     // cat inexistente? ignora
                    }

                    // quantos já existem p/ definir sequência
                    $already = ConvocationListSeat::where([
                        'convocation_list_id'        => $list->id,
                        'course_id'                  => $course->id,
                        'origin_admission_category_id'=> $catId,
                    ])->count();

                    // cria N linhas
                    for ($i = 1; $i <= $qty; $i++) {
                        $seq = $already + $i;

                        ConvocationListSeat::create([
                            'convocation_list_id'         => $list->id,
                            'course_id'                   => $course->id,
                            'origin_admission_category_id'=> $catId,
                            'current_admission_category_id'=> $catId,
                            'status'                      => 'open',
                            'seat_code'                   => ConvocationListSeat::makeSeatCode(
                                $list->process_selection_id,
                                $list->id,
                                $course->name,
                                $catName,
                                $seq
                            ),
                        ]);
                        $createdCounter++;
                    }
                }
            }
        });

        return $createdCounter;    // para retornar ao controller
    }
}
