<?php

namespace App\Services;

use App\Models\ProcessSelection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessSelectionApplicationCsvService
{

    public function export(
        ProcessSelection $selection,
        ?int $enemYear = null,
        bool $onlyEnemNumbers = false
    ): StreamedResponse {
        // Ramificação: modo normal x modo "só ENEM"
        if ($onlyEnemNumbers) {
            return $this->exportOnlyEnemNumbersZip($selection, $enemYear);
        }

        return $this->exportFullCsvPerCategory($selection, $enemYear);
    }

    /**
     * Modo "normal": CSV único, cabeçalho, 1 linha por categoria de ingresso.
     * Mantém o comportamento original, com a adição apenas do filtro por enem_year.
     */
    protected function exportFullCsvPerCategory(
        ProcessSelection $selection,
        ?int $enemYear = null
    ): StreamedResponse {
        $filename = "inscricoes_process_{$selection->id}.csv";

        return response()->streamDownload(function () use ($selection, $enemYear) {
            $handle = fopen('php://output', 'w');

            // Cabeçalho CSV (como era antes)
            fputcsv($handle, [
                'application_id',
                'user_id',
                'nome',
                'email',
                'cpf',
                'birthdate',
                'sex',
                'phone',
                'address',
                'uf',
                'city',
                'enem_number',
                'enem_year',
                'course_name',
                'campus',
                'admission_category',
                'bonus',
                'created_at',
            ]);

            // Query base
            $query = $selection->applications()
                ->with('user')
                ->orderBy('id');

            // Filtro opcional por ano do ENEM no JSON form_data.enem_year
            if (!is_null($enemYear)) {
                $query->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.enem_year')) = ?",
                    [$enemYear]
                );
            }

            $query->chunk(100, function ($apps) use ($handle) {
                foreach ($apps as $app) {
                    $user = $app->user;
                    $data = $app->form_data ?? [];

                    $position   = $data['position'] ?? [];
                    $courseName = $position['name'] ?? '';
                    $campus     = $position['academic_unit']['name'] ?? '';
                    $enemNumber = $data['enem'] ?? '';
                    $enemYear   = $data['enem_year'] ?? '';
                    $bonus      = $data['bonus']['name'] ?? '';
                    $categories = $data['admission_categories'] ?? [];

                    // Uma linha por categoria
                    foreach ($categories as $cat) {
                        $categoryName = $cat['name'] ?? '';

                        fputcsv($handle, [
                            $app->id,
                            $app->user_id,
                            $user->name,
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
                            $courseName,
                            $campus,
                            $categoryName,
                            $bonus,
                            $app->created_at?->toDateTimeString(),
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


    protected function exportOnlyEnemNumbersZip(
        ProcessSelection $selection,
        ?int $enemYear = null
    ): StreamedResponse {
        $zipFileName = "notas-enem-{$enemYear}-processo-{$selection->id}.zip";

        return response()->streamDownload(function () use ($selection, $enemYear) {

            // Caminho temporário do ZIP
            $tmpZip = tempnam(sys_get_temp_dir(), 'zip_');

            $zip = new \ZipArchive();

            if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Erro ao criar pacote ZIP.');
            }

            $baseQuery = $selection->applications()->orderBy('id');

            if (!is_null($enemYear)) {
                $baseQuery->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.enem_year')) = ?", [$enemYear]);
            }

            $totalApplications = (clone $baseQuery)->count();

            if ($totalApplications === 0) {
                $zip->close();
                readfile($tmpZip);
                unlink($tmpZip);
                return;
            }

            $perPage = 1000;
            $totalPages = (int) ceil($totalApplications / $perPage);
            $currentPage = 1;

            $baseQuery->chunk($perPage, function ($apps) use (&$currentPage, $enemYear, $totalPages, $zip) {

                $innerFileName = sprintf(
                    "inscricoes-page-%d-de-%d-%d.txt",
                    $currentPage,
                    $totalPages,
                    $enemYear
                );

                // Monta o conteúdo do txt
                $stream = fopen('php://temp', 'r+');

                foreach ($apps as $app) {
                    $data = $app->form_data ?? [];
                    $enemNumber = $data['enem'] ?? '';

                    if ($enemNumber !== '') {
                        fwrite($stream, $enemNumber . PHP_EOL);
                    }
                }

                rewind($stream);
                $content = stream_get_contents($stream);
                fclose($stream);

                // Adiciona ao ZIP
                $zip->addFromString($innerFileName, $content);

                $currentPage++;
            });

            $zip->close();

            // envia ZIP para saída
            readfile($tmpZip);
            unlink($tmpZip);
        }, $zipFileName, [
            'Content-Type'  => 'application/zip',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
