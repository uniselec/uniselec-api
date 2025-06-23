<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationOutcome;
use App\Models\EnemScore;

class ProcessApplicationOutcomeWithoutPending
{

    public function __construct(private int $processSelectionId) {}

    public function process()
    {
        $this->ensureAllApplicationsHaveOutcomes();
        $this->processEnemScores();
    }

    private function ensureAllApplicationsHaveOutcomes()
    {
        $applications = Application::where('process_selection_id', $this->processSelectionId)
            ->doesntHave('applicationOutcome')
            ->get();

        foreach ($applications as $application) {
            $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Resultado Não Processado');
        }
    }

    private function processEnemScores()
    {
        $enemScores = EnemScore::with('application')
            ->whereHas(
                'application',
                fn($q) =>
                $q->where('process_selection_id', $this->processSelectionId)
            )->get();

        $processedApplicationIds = [];

        foreach ($enemScores as $enemScore) {
            $application = $enemScore->application;
            $processedApplicationIds[] = $application->id;

            $averageScore = $this->calculateAverageScore($enemScore->scores);
            $finalScore = $this->applyBonus($averageScore, $application->form_data['bonus'] ?? []);

            $reasons = [];

            if ($enemScore->scores['cpf'] !== $application->form_data['cpf']) {
                $reasons[] = 'Inconsistência no CPF';
            }

            if ($this->normalizeString($enemScore->scores['name']) !== $this->normalizeString($application->form_data['name'])) {
                $reasons[] = 'Inconsistência no Nome';
            }

            if (isset($application->form_data['birthdate']) && isset($enemScore->scores['birthdate'])) {
                $applicationBirthdate = \DateTime::createFromFormat('Y-m-d', $application->form_data['birthdate']);
                $enemScoreBirthdate = \DateTime::createFromFormat('d/m/Y', $enemScore->scores['birthdate']);

                if (!$applicationBirthdate || !$enemScoreBirthdate || $applicationBirthdate->format('Y-m-d') !== $enemScoreBirthdate->format('Y-m-d')) {
                    $reasons[] = 'Inconsistência na Data de Nascimento';
                }
            } else {
                $reasons[] = 'Data de Nascimento ausente ou inconsistente';
            }

            $this->createOrUpdateOutcomeForApplication($application, 'approved', implode('; ', $reasons), $averageScore, $finalScore);
        }

        $applicationsWithoutEnemScore = Application::whereNotIn('id', $processedApplicationIds)->get();
        foreach ($applicationsWithoutEnemScore as $application) {
            $this->createOrUpdateOutcomeForApplication($application, 'rejected', 'Inscrição do ENEM não Identificada');
        }
    }

    private function calculateAverageScore($scores)
    {
        $totalScore = $scores['science_score'] + $scores['humanities_score'] + $scores['language_score'] + $scores['math_score'] + $scores['writing_score'];
        return $totalScore / 5;
    }

    private function applyBonus($averageScore, $bonuses)
    {
        $finalScore = $averageScore;
        // foreach ($bonuses as $bonus) {
        //     if (strpos($bonus, '10%') !== false) {
        //         $finalScore *= 1.10;
        //     } elseif (strpos($bonus, '20%') !== false) {
        //         $finalScore *= 1.20;
        //     }
        // }
        return $finalScore;
    }

    private function createOrUpdateOutcomeForApplication($application, $status, $reason = null, $averageScore = '0.00', $finalScore = '0.00')
    {
        ApplicationOutcome::updateOrCreate(
            ['application_id' => $application->id],
            [
                'status' => $status,
                'classification_status' => 'classifiable',
                'average_score' => $averageScore,
                'final_score' => $finalScore,
                'reason' => $reason,
            ]
        );
    }

    private function normalizeString($string)
    {
        if ($string !== mb_convert_encoding(mb_convert_encoding($string, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))
            $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
        $string = htmlentities($string, ENT_NOQUOTES, 'UTF-8');
        $string = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '\1', $string);
        $string = html_entity_decode($string, ENT_NOQUOTES, 'UTF-8');
        $string = preg_replace(array('`[^a-z0-9]`i', '`[-]+`'), ' ', $string);
        $string = preg_replace('/( ){2,}/', '$1', $string);
        $string = strtoupper(trim($string));
        return $string;
    }
}
