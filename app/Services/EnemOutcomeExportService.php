<?php

namespace App\Services;

use App\Models\ProcessSelection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnemOutcomeExportService
{
    /**
     * Exporta CSV integrando inscrições, notas ENEM e outcomes.
     *
     * @param  ProcessSelection  $selection
     * @param  int|null          $enemYear
     * @return StreamedResponse
     */
    public function export(ProcessSelection $selection, ?int $enemYear): StreamedResponse
    {
        $filename = "inscricoes_enem_outcomes_{$selection->id}.csv";

        return response()->streamDownload(function () use ($selection, $enemYear) {
            $handle = fopen('php://output', 'w');

            // Cabeçalho sem ranking
            fputcsv($handle, [
                'ID Inscrição',
                'Nome',
                'Nome Social',
                'Email',
                'CPF',
                'Data de Nascimento',
                'Sexo',
                'Telefone',
                'Endereço',
                'UF',
                'Cidade',
                'Número ENEM',
                'Ano ENEM',
                'Ciências da Natureza',
                'Ciências Humanas',
                'Linguagens',
                'Matemática',
                'Redação',
                'Média',
                'Nota Final',
                'Situação',
                'Motivo da Decisão',
                'Curso',
                'Campus',
                'Categoria de Ingresso',
                'Bônus',
                'Data de Inscrição',
                'Nota do Enem Original',
            ]);



            $query = $selection->applications()
                ->with(['user', 'enemScore', 'applicationOutcome'])
                ->orderBy('id');

            if ($enemYear !== null) {
                $query->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.enem_year')) = ?",
                    [$enemYear]
                );
            }

            $query->chunk(100, function ($apps) use ($handle) {
                foreach ($apps as $app) {
                    $user    = $app->user;
                    $data    = $app->form_data;
                    $score   = $app->enemScore?->scores     ?? [];
                    $outcome = $app->applicationOutcome     ?? null;

                    // traduz o status
                    $statusMap = [
                        'pending'  => 'Pendente',
                        'approved' => 'Deferido',
                        'rejected' => 'Indeferido',
                    ];
                    $statusLabel = $statusMap[$outcome->status ?? ''] ?? '';

                    $position   = $data['position']               ?? [];
                    $courseName = $position['name']               ?? '';
                    $campus     = $position['academic_unit']['name'] ?? '';
                    $enemNumber = $data['enem']                   ?? '';
                    $enemYear   = $data['enem_year']              ?? '';
                    $bonus      = $data['bonus']['name']          ?? '';
                    $categories = $data['admission_categories']   ?? [];

                    foreach ($categories as $cat) {
                        fputcsv($handle, [
                            $app->id,
                            $app->form_data['name'] ?? '',
                            $app->form_data['social_name'] ?? '',
                            $user->email,
                            $user->cpf,
                            $data['birthdate'] ?? '',
                            $data['sex']       ?? '',
                            $data['phone1']    ?? '',
                            $data['address']   ?? '',
                            $data['uf']        ?? '',
                            $data['city']      ?? '',
                            $enemNumber,
                            $enemYear,
                            $score['science_score']    ?? 0,
                            $score['humanities_score'] ?? 0,
                            $score['language_score']   ?? 0,
                            $score['math_score']       ?? 0,
                            $score['writing_score']    ?? 0,
                            // status traduzido

                            $outcome->average_score ?? '',
                            $outcome->final_score   ?? '',
                            $statusLabel,
                            $outcome->reason        ?? '',
                            $courseName,
                            $campus,
                            $cat['name'] ?? '',
                            $bonus,
                            $app->created_at?->toDateTimeString(),
                            $app->enemScore?->original_scores ?? "",
                        ]);
                    }
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type'  => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
