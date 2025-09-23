<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationOutcome;
use App\Models\EnemScore;
use App\Models\KnowledgeArea;
use App\Models\ProcessSelection;
use Carbon\Carbon;

class ProcessApplicationOutcome
{
    public function __construct(private int $processSelectionId) {}

    public function process()
    {
        $this->ensureAllApplicationsHaveOutcomes();
        $this->processEnemScores();
        $this->markDuplicateApplications();
        ProcessSelection::find($this->processSelectionId)
            ->update(['last_applications_processed_at' => Carbon::now()]);
    }

    /* ---------------------------------------------------------------------- */
    /* ----------------------   1) OUTCOME PLACEHOLDERS   -------------------- */
    /* ---------------------------------------------------------------------- */

    private function ensureAllApplicationsHaveOutcomes(): void
    {
        // Remove todos os ApplicationOutcomes deste processo seletivo
        ApplicationOutcome::whereIn(
            'application_id',
            Application::where('process_selection_id', $this->processSelectionId)->pluck('id')
        )->delete();

        Application::where('process_selection_id', $this->processSelectionId)
            ->doesntHave('applicationOutcome')
            ->each(fn ($app) =>
                $this->createOrUpdateOutcomeForApplication(
                    $app,
                    'pending',
                    'Resultado Não Processado'
                )
            );
    }

    /* ---------------------------------------------------------------------- */
    /* ----------------------   2) PROCESSAMENTO ENEM    --------------------- */
    /* ---------------------------------------------------------------------- */

    private function processEnemScores(): void
    {
        $enemScores = EnemScore::with('application')
            ->whereHas('application', fn ($q) =>
                $q->where('process_selection_id', $this->processSelectionId)
            )
            ->get();

        $processedIds = [];

        $processSelection = ProcessSelection::find($this->processSelectionId);
        $knowledgeArea = KnowledgeArea::all();
        $minimumScores = collect($processSelection->courses)
        ->map(fn ($course) => [
            'id' => $course['id'],
            'name' => $course['name'],
            'minimumScores' => $course['minimumScores']
        ])->values()->toArray();

        foreach ($enemScores as $enemScore) {
            $application      = $enemScore->application;
            $processedIds[]   = $application->id;
            $applicationData  = $application->form_data ?? [];

            /* --- inscrição não localizada --- */
            if (str_contains($enemScore->original_scores, 'Candidato não encontrado')) {
                $this->createOrUpdateOutcomeForApplication(
                    $application,
                    'rejected',
                    'Inscrição do ENEM não Identificada'
                );
                continue;
            }

            /* --- cálculo da média e bônus ---------------------------------- */
            $averageScore = $this->calculateAverageScore($enemScore->scores);

            $bonusObject  = $applicationData['bonus'] ?? null;
            $finalScore   = $this->applyBonus($averageScore, $bonusObject);

            /* --- consistências -------------------------------------------- */
            $reasons = [];

            if ((($enemScore->scores['cpf'] ?? '') !== ($applicationData['cpf'] ?? '')) && !$application->cpf_source) {
                $reasons[] = 'Inconsistência no CPF';
            }

            if (($this->normalizeString($enemScore->scores['name'] ?? '') !==
                $this->normalizeString($applicationData['name'] ?? '') && !$application->name_source)) {
                $reasons[] = 'Inconsistência no Nome';
            }

            $birthdateInconsistency = false;
            if (!$application->name_source) {
                if (($applicationData['birthdate'] ?? null) && ($enemScore->scores['birthdate'] ?? null)) {
                    $appDate  = \DateTime::createFromFormat('Y-m-d', $applicationData['birthdate']);
                    $enemDate = \DateTime::createFromFormat('d/m/Y', $enemScore->scores['birthdate']);
                    if (!$appDate || !$enemDate || $appDate->format('Y-m-d') !== $enemDate->format('Y-m-d')) {
                        $reasons[] = 'Inconsistência na Data de Nascimento';
                        $birthdateInconsistency = true;
                    }
                } else {
                    $reasons[] = 'Data de Nascimento ausente ou inconsistente';
                }
            }

            /* --- análise da nota mínima ------------------------------ */

            // Filtra a pontução mínima do curso escolhido pelo candidato.
            $candidateCourseMinimumScores = array_filter($minimumScores, fn ($item) => $item['id'] === $applicationData['position']['id']);
            $candidateCourseMinimumScores = reset($candidateCourseMinimumScores)['minimumScores'] ?? null;

            $studentScores = $enemScore->scores;

            $message = "Indeferido por não atingir a nota mínima em ";

            // Filtra as áreas em que o candidato não atingiu a nota mínima
            $failedScoreNames = collect($candidateCourseMinimumScores)
            ->filter(function ($min, $key) use ($studentScores) {
                return isset($studentScores[$key])
                && floatval($studentScores[$key]) < floatval($min);
            })
            ->keys()
            ->toArray();

            // Verifica se houve ao menos uma reprovação por nota mínima
            if ($rejectedByMinimumScore = count($failedScoreNames) > 0) {
                
                // Transforma os slugs das áreas reprovadas em suas descrições legíveis
                $failedDescriptions = collect($failedScoreNames)
                ->map(fn ($key) => $knowledgeArea->firstWhere('slug', $key)->name)
                ->toArray();

                // Monta a string final da mensagem
                $message .= implode(', ', array_slice($failedDescriptions, 0, -1))
                    . (count($failedDescriptions) > 1 ? ' e ' : '')
                    . end($failedDescriptions);

                $reasons[] = $message;
            }

            /* --- regra de decisão ----------------------------------------- */
            if ($rejectedByMinimumScore || (count($reasons) === 3)) {
                $status = 'rejected';
            } elseif (count($reasons) === 1 && $birthdateInconsistency) {
                $status = 'approved';
            } elseif (!empty($reasons)) {
                $status = 'pending';
            } else {
                $status = 'approved';
            }

            $this->createOrUpdateOutcomeForApplication(
                $application,
                $status,
                empty($reasons) ? null : implode('; ', $reasons),
                $averageScore,
                $finalScore
            );
        }

        /* --- aplicações SEM registro de ENEM ------------------------------ */
        Application::whereNotIn('id', $processedIds)
            ->where('process_selection_id', $this->processSelectionId)
            ->get()
            ->each(fn ($app) =>
                $this->createOrUpdateOutcomeForApplication(
                    $app,
                    'rejected',
                    'Inscrição do ENEM não Identificada'
                )
            );
    }

    /* ---------------------------------------------------------------------- */
    /* ----------------------   3) DUPLICIDADE DE INSCR.  -------------------- */
    /* ---------------------------------------------------------------------- */

    private function markDuplicateApplications(): void
    {
        Application::select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id')
            ->each(function ($userId) {
                $apps = Application::where('user_id', $userId)
                    ->orderBy('created_at')
                    ->get();

                // Mantém apenas a inscrição mais recente
                $apps->slice(0, $apps->count() - 1)
                    ->each(fn ($app) =>
                        $this->createOrUpdateOutcomeForApplication(
                            $app,
                            'rejected',
                            'Inscrição duplicada'
                        )
                    );
            });
    }

    /* ---------------------------------------------------------------------- */
    /* ---------------------------   HELPERS   ------------------------------ */
    /* ---------------------------------------------------------------------- */

    private function calculateAverageScore(array $scores): float
    {
        $sum = $scores['science_score'] + $scores['humanities_score']
             + $scores['language_score'] + $scores['math_score']
             + $scores['writing_score'];

        return $sum / 5;
    }

    /**
     * Aplica um único bônus (objeto) sobre a média.
     * Espera campo "value" em percentual (ex.: "10.00") — fallback para
     * porcentagem contida em "name".
     */
    private function applyBonus(float $average, array|null $bonus): float
    {

        if (!$bonus) {
            return $average;
        }

        // 1) valor explícito

        if (isset($bonus['value']) && is_numeric($bonus['value'])) {
            return $average * (1 + floatval($bonus['value']) / 100);
        }

        // // 2) fallback: procura "10%" / "20%" no name
        // if (isset($bonus['name'])) {
        //     if (str_contains($bonus['name'], '20%')) {
        //         return $average * 1.20;
        //     }
        //     if (str_contains($bonus['name'], '10%')) {
        //         return $average * 1.10;
        //     }
        // }

        return $average;
    }

    private function createOrUpdateOutcomeForApplication(
        Application $application,
        string      $status,
        ?string     $reason       = null,
        float|string $average     = '0.00',
        float|string $final       = '0.00'
    ): void
    {
        ApplicationOutcome::updateOrCreate(
            ['application_id' => $application->id],
            [
                'status'                => $status,
                'classification_status' => $status === 'approved' ? 'classifiable' : null,
                'average_score'         => $average,
                'final_score'           => $final,
                'reason'                => $reason,
            ]
        );
    }

    private function normalizeString(string $str): string
    {
        $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        $str = htmlentities($str, ENT_NOQUOTES, 'UTF-8');
        $str = preg_replace(
            '`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i',
            '$1',
            $str
        );
        $str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
        $str = preg_replace(['`[^a-z0-9]`i', '`[-]+`'], ' ', $str);
        $str = preg_replace('/( ){2,}/', '$1', $str);

        return strtoupper(trim($str));
    }
}
