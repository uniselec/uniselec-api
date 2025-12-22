<?php

namespace App\Services;

use App\Models\ConvocationList;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ConvocationPdfExportService
{
    public function export(ConvocationList $list): Response
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $selectionName = optional($list->processSelection)->name ?? 'Processo Seletivo';
        $listName      = $list->name ?? 'Lista';
        $publishedAt   = optional($list->published_at)->format('d/m/Y H:i');

        /**
         * Importante:
         * - Se a.name_source = 'enem' → exibir enem_scores.scores.name
         * - Senão → exibir form_data.social_name (se houver) ou form_data.name
         */
        $rows = DB::table('convocation_list_applications as cla')
            ->join('applications as a', 'a.id', '=', 'cla.application_id')
            ->join('courses as c', 'c.id', '=', 'cla.course_id')
            ->join('admission_categories as ac', 'ac.id', '=', 'cla.admission_category_id')

            // enem_scores pode não existir → LEFT JOIN
            ->leftJoin('enem_scores as es', 'es.application_id', '=', 'a.id')

            ->where('cla.convocation_list_id', $list->id)
            ->whereIn('cla.convocation_status', ['called', 'called_out_of_quota'])
            ->select([
                'cla.course_id',
                'c.name as course_name',
                'cla.admission_category_id',
                'ac.name as category_name',
                'cla.category_ranking',
                'cla.convocation_status',
                'a.form_data',
                'a.name_source',
                'es.scores as enem_scores', // JSON (string) no MariaDB
            ])
            ->orderBy('c.name')
            ->orderBy('ac.name')
            ->orderByRaw('cla.category_ranking IS NULL') // nulls por último
            ->orderBy('cla.category_ranking')
            ->get();

        $groupsMap = [];

        foreach ($rows as $r) {

            $key = $r->course_id . ':' . $r->admission_category_id;

            $formData  = $this->decodeFormData($r->form_data);
            $enemScore = $this->decodeFormData($r->enem_scores);

            $name = $this->resolveApplicationName(
                nameSource: $r->name_source ?? null,
                formData: $formData,
                enemScore: $enemScore
            );

            $cpf  = (string)($formData['cpf'] ?? '');

            $groupsMap[$key] ??= [
                'course_name'   => $r->course_name,
                'category_name' => $r->category_name,
                'items'         => [],
            ];

            $groupsMap[$key]['items'][] = [
                'category_ranking' => $r->category_ranking,
                'name'             => $name,
                'cpf_masked'       => $this->maskCpf($cpf),
                'status_label'     => $this->mapConvocationStatus($r->convocation_status),
            ];
        }

        // Ordenação dentro do grupo: category_ranking, depois nome (desempate)
        $groups = array_values(array_map(function ($group) {
            usort($group['items'], function ($a, $b) {
                $ra = $a['category_ranking'];
                $rb = $b['category_ranking'];

                // nulls por último
                if ($ra === null && $rb !== null) return 1;
                if ($ra !== null && $rb === null) return -1;

                if ($ra !== $rb) return ($ra <=> $rb);

                return mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
            });

            return $group;
        }, $groupsMap));

        $pdf = Pdf::loadView('pdf.convocation_list', [
            'selectionName' => $selectionName,
            'listName'      => $listName,
            'publishedAt'   => $publishedAt,
            'groups'        => $groups,
        ]);

        $filename = 'convocacao_' . $list->id . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function resolveApplicationName(?string $nameSource, array $formData, array $enemScore): string
    {
        // Regra: se name_source = enem → usar enem_scores.scores.name

        if ($nameSource === 'enem') {
            $enemName = $enemScore['name'];

            if (is_string($enemName) && trim($enemName) !== '') {
                return $enemName;
            }
        }

        // Fallback padrão do seu sistema
        $socialName = $formData['social_name'] ?? null;
        if (is_string($socialName) && trim($socialName) !== '') {
            return $socialName;
        }

        $name = $formData['name'] ?? null;
        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return 'N/D';
    }

    private function mapConvocationStatus(?string $status): string
    {
        return match ($status) {
            'called'              => 'Convocado',
            'called_out_of_quota' => 'Convocado fora de vaga',
            default               => '—',
        };
    }

    private function decodeFormData($formData): array
    {
        // No DB::table normalmente vem string JSON; pode vir null
        if (is_array($formData)) return $formData;

        if (is_string($formData) && $formData !== '') {
            $decoded = json_decode($formData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function maskCpf(string $cpf): string
    {
        $clean = preg_replace('/\D/', '', $cpf) ?? '';
        if (strlen($clean) !== 11) return $cpf;

        return '***.' . substr($clean, 3, 3) . '.' . substr($clean, 6, 3) . '-**';
    }
}
