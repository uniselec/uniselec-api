<?php

namespace App\Services;

use App\Models\ConvocationList;
use RuntimeException;
use ZipArchive;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvocationListCsvExportService
{
    /**
     * Gera um ZIP com um CSV de convocações por curso.
     *
     * @param  ConvocationList  $list
     * @return StreamedResponse
     */
    public function exportCsv(ConvocationList $list): StreamedResponse
    {
        $zipFileName = "convocation_list_{$list->id}.zip";

        return response()->streamDownload(function () use ($list, $zipFileName) {
            // cria ZIP temporário
            $tmpZip = tempnam(sys_get_temp_dir(), 'convoc_zip_');
            $zip = new ZipArchive();
            if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Não foi possível criar o ZIP em {$tmpZip}");
            }

            // carrega inscrições aprovadas ordenadas
            $applications = $list->applications()
                ->whereIn('convocation_status', ['called','called_out_of_quota'])
                ->with(['course', 'category'])
                ->orderBy('course_id')
                ->orderBy('category_ranking')
                ->get();

            // agrupa por curso
            $byCourse = $applications->groupBy('course_id');

            foreach ($byCourse as $courseId => $apps) {
                $courseName = $apps->first()->course->slug ?? "course_{$courseId}";
                $csvName    = "convoc_course_{$courseId}.csv";

                // gera CSV em memória
                $handle = fopen('php://temp', 'r+');
                // cabeçalho
                fputcsv($handle, [
                    'application_id',
                    'user_id',
                    'name',
                    'convocation_status',
                    'category_ranking',
                ]);

                foreach ($apps as $app) {
                    fputcsv($handle, [
                        $app->id,
                        $app->user_id,
                        $app->form_data['name'] ?? '',
                        $app->convocation_status,
                        $app->category_ranking,
                    ]);
                }

                rewind($handle);
                $content = stream_get_contents($handle);
                fclose($handle);

                // adiciona ao ZIP
                $zip->addFromString($csvName, $content);
            }

            $zip->close();

            // devolve o zip para o output
            readfile($tmpZip);
            unlink($tmpZip);
        }, $zipFileName, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$zipFileName}\"",
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }
}
