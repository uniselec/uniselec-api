<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSelection;
use Illuminate\Http\Request;

class ProcessSelectionCourseController extends Controller
{
    public function sync(Request $request, $processSelectionId)
    {
        $validated = $request->validate([
            'courses' => 'required|array',
            'courses.*.id' => 'exists:courses,id',
            'courses.*.vacancies' => 'integer|min:0',
        ]);

        $processSelection = ProcessSelection::findOrFail($processSelectionId);

        $formattedCourses = collect($validated['courses'])
            ->mapWithKeys(function ($course) {
                return [$course['id'] => ['vacancies' => $course['vacancies']]];
            })->toArray();

        $processSelection->courses()->syncWithoutDetaching($formattedCourses);

        $attachedCourses = $processSelection->courses()
            ->withPivot(['vacancies', 'created_at', 'updated_at'])
            ->get();

        return response()->json([
            'message' => 'Cursos vinculados com sucesso ao processo seletivo.',
            'attached_courses' => $attachedCourses,
        ], 200);
    }

    public function remove(Request $request)
    {
        $validated = $request->validate([
            'process_selection_id' => 'required|exists:process_selections,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $processSelection = ProcessSelection::findOrFail($validated['process_selection_id']);
        $processSelection->courses()->detach($validated['course_id']);

        $remainingCourses = $processSelection->courses()
            ->withPivot(['vacancies', 'created_at', 'updated_at'])
            ->get();

        return response()->json([
            'message' => 'Curso removido com sucesso do processo seletivo.',
            'remaining_courses' => $remainingCourses,
        ], 200);
    }
}
