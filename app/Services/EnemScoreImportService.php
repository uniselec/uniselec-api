<?php

namespace App\Services;

use League\Csv\Reader;
use League\Csv\Statement;
use Illuminate\Support\Facades\DB;
use App\Models\Application;
use App\Models\EnemScore;
use Illuminate\Support\Arr;
use App\Models\ApplicationOutcome;

class EnemScoreImportService
{
    public function import(\SplFileInfo|\Stringable $file, int $processId): array
    {
        ApplicationOutcome::whereHas('application', function ($query) use ($processId) {
            $query->where('process_selection_id', $processId);
        })->delete();

        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setDelimiter(';');
        $records = (new Statement())->process($csv);

        $summary = [ 'processed'=>0,'created'=>0,'updated'=>0,'not_found'=>0,'errors'=>0 ];

        DB::transaction(function () use ($records, $processId, &$summary) {
            foreach ($records as $row) {
                $summary['processed']++;

                $enem = trim($row[0] ?? '');
                if ($enem === '') continue;

                $apps = Application::query()
                    ->where('process_selection_id', $processId)
                    ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(form_data,"$.enem")) = ?', [$enem])
                    ->get();

                if ($apps->isEmpty()) {
                    $summary['not_found']++;
                    continue;
                }

                $payload = $this->buildPayload($row);

                foreach ($apps as $app) {
                    $created = EnemScore::updateOrCreate(
                        ['enem'=>$payload['enem'], 'application_id'=>$app->id],
                        Arr::except($payload, ['enem'])
                    )->wasRecentlyCreated;

                    $created ? $summary['created']++ : $summary['updated']++;
                }
            }
        });

        return $summary;
    }

    private function buildPayload(array $row): array
    {
        $notFound = ($row[1] ?? '') === 'Candidato não encontrado';

        return [
            'enem'            => trim($row[0]),
            'scores'          => [
                'name'              => $notFound ? 'N/A' : $row[2]  ?? '',
                'cpf'               => $notFound ? 'N/A' : $row[1]  ?? '',
                'birthdate'         => $notFound ? 'N/A' : $row[13] ?? '',
                'science_score'     => $notFound ? 0 : $row[3]  ?? 0,
                'humanities_score'  => $notFound ? 0 : $row[4]  ?? 0,
                'language_score'    => $notFound ? 0 : $row[5]  ?? 0,
                'math_score'        => $notFound ? 0 : $row[6]  ?? 0,
                'writing_score'     => $notFound ? 0 : $row[7]  ?? 0,
            ],
            'original_scores' => implode(';', $row),
        ];
    }
}
