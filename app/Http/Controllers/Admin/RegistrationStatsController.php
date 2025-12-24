<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class RegistrationStatsController extends Controller
{
    /**
     * Retorna contagem de inscrições por categoria de ingresso.
     *
     * GET /admin/stats/by-admission-category
     */
    public function byAdmissionCategory(Request $request)
    {
        // Carrega somente o form_data para economizar memória
        $applications = Application::query()
            ->get(['form_data']);

        $counts = [];

        foreach ($applications as $app) {
            $categories = data_get($app->form_data, 'admission_categories', []);
            foreach ($categories as $cat) {
                $name = $cat['name'] ?? '—';
                if (! isset($counts[$name])) {
                    $counts[$name] = 0;
                }
                $counts[$name]++;
            }
        }

        // Transformar em coleção ordenada por total decrescente
        $result = collect($counts)
            ->map(function ($total, $category) {
                return ['admission_category' => $category, 'total' => $total];
            })
            ->sortByDesc('total')
            ->values();

        return response()->json($result);
    }

    /**
     * Retorna contagem de inscrições por curso.
     *
     * GET /admin/stats/by-course
     */
    public function byCourse(Request $request)
    {
        $data = Application::query()
            ->selectRaw("
                JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.position.name')) AS course_name,
                COUNT(*) AS total
            ")
            ->groupBy('course_name')
            ->orderByDesc('total')
            ->get();

        return response()->json($data);
    }

    /**
     * Retorna contagem de inscrições por campus (academic_unit).
     *
     * GET /admin/stats/by-campus
     */
    public function byCampus(Request $request)
    {
        $data = Application::query()
            ->selectRaw("
                JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.position.academic_unit.name')) AS campus_name,
                COUNT(*) AS total
            ")
            ->groupBy('campus_name')
            ->orderByDesc('total')
            ->get();

        return response()->json($data);
    }

    public function byCourseCategory(Request $request)
    {
        // Carrega só o form_data
        $applications = Application::query()
            ->get(['form_data']);

        $counts = [];

        foreach ($applications as $app) {
            $course = data_get($app->form_data, 'position.name', '—');
            $categories = data_get($app->form_data, 'admission_categories', []);

            foreach ($categories as $cat) {
                $catName = $cat['name'] ?? '—';
                if (! isset($counts[$course])) {
                    $counts[$course] = [];
                }
                if (! isset($counts[$course][$catName])) {
                    $counts[$course][$catName] = 0;
                }
                $counts[$course][$catName]++;
            }
        }

        // Achata o array para formato plano
        $result = [];
        foreach ($counts as $course => $cats) {
            foreach ($cats as $catName => $total) {
                $result[] = [
                    'course_name'        => $course,
                    'admission_category' => $catName,
                    'total'              => $total,
                ];
            }
        }

        // Ordena desc por curso e total
        usort($result, function ($a, $b) {
            if ($a['course_name'] === $b['course_name']) {
                return $b['total'] <=> $a['total'];
            }
            return strcmp($a['course_name'], $b['course_name']);
        });

        return response()->json($result);
    }
}
