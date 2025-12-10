<?php

namespace App\Services;

use App\Models\ProcessSelection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnemScoreExportService
{
    /**
     * Exporta CSV com:
     * - 1 linha por categoria de ingresso
     * - dados do usuário, curso, campus, categoria e nota ENEM (se existir)
     */
    public function export(ProcessSelection $selection, ?int $enemYear = null): StreamedResponse
    {
        $filename = "inscricoes_enem_scores_{$selection->id}.csv";

        return response()->streamDownload(function () use ($selection, $enemYear) {
            $handle = fopen('php://output', 'w');

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
                'Município',
                'Número ENEM',
                'Ano ENEM',
                'Ciências da Natureza',
                'Ciências Humanas',
                'Linguagens',
                'Matemática',
                'Redação',
                'Curso',
                'Campus',
                'Categoria de Ingresso',
                'Bônus',
                'Data da Inscrição',
                'Nota do Enem Original',
            ]);

            $query = $selection->applications()
                ->with(['user', 'enemScore'])
                ->orderBy('id');

            if (!is_null($enemYear)) {
                $query->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.enem_year')) = ?",
                    [$enemYear]
                );
            }

            $query->chunk(100, function ($apps) use ($handle) {
                foreach ($apps as $app) {
                    $user = $app->user;
                    $data = $app->form_data;
                    $score = $app->enemScore?->scores ?? [];

                    $position   = $data['position'] ?? [];
                    $courseName = $position['name'] ?? '';
                    $campus     = $position['academic_unit']['name'] ?? '';
                    $enemNumber = $data['enem'] ?? '';
                    $enemYear   = $data['enem_year'] ?? '';
                    $bonus      = $data['bonus']['name'] ?? '';
                    $categories = $data['admission_categories'] ?? [];

                    foreach ($categories as $cat) {
                        fputcsv($handle, [
                            $app->id,
                            $app->form_data['name'] ?? '',
                            $app->form_data['social_name'] ?? '',
                            $user->email,
                            $user->cpf,
                            $data['birthdate'] ?? '',
                            $data['sex'] ?? '',
                            $data['phone1'] ?? '',
                            $data['address'] ?? '',
                            $data['uf'] ?? '',
                            $data['city'] ?? '',
                            $enemNumber,
                            $enemYear,
                            $score['science_score']   ?? 0,
                            $score['humanities_score'] ?? 0,
                            $score['language_score']  ?? 0,
                            $score['math_score']      ?? 0,
                            $score['writing_score']   ?? 0,
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
