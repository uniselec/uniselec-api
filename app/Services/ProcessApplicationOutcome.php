<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationOutcome;
use App\Models\EnemScore;

class ProcessApplicationOutcome
{
    public function process()
    {
        $this->ensureAllApplicationsHaveOutcomes();

        $this->processEnemScores();
    }

    private function ensureAllApplicationsHaveOutcomes()
    {
        $applications = Application::doesntHave('applicationOutcome')->get();

        foreach ($applications as $application) {
            $this->createOrUpdateOutcomeForApplication($application, 'rejected', 'Número de inscrição inválido');
        }
    }

    private function processEnemScores()
    {
        $enemScores = EnemScore::with('application')->get();

        foreach ($enemScores as $enemScore) {
            $application = $enemScore->application;


            if ($enemScore->scores['cpf'] !== $application->data['cpf']) {
                $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Inconsistência no CPF');
                continue;
            }


            if ($this->normalizeString($enemScore->scores['name']) !== $this->normalizeString($application->data['name'])) {
                $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Inconsistência no Nome');
                continue;
            }


            $applicationBirtdate = \DateTime::createFromFormat('Y-m-d', $application->data['birthdate']);
            $enemScoreBirthdate = \DateTime::createFromFormat('d/m/Y', $enemScore->scores['birthdate']);

            if (!$applicationBirtdate || !$enemScoreBirthdate || $applicationBirtdate->format('Y-m-d') !== $enemScoreBirthdate->format('Y-m-d')) {
                $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Inconsistência na Data de Nascimento');
                continue;
            }


            $averageScore = $this->calculateAverageScore($enemScore->scores);
            $finalScore = $this->applyBonus($averageScore, $application->data['bonus']);
            $this->createOrUpdateOutcomeForApplication($application, 'approved', null, $averageScore, $finalScore);
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
        foreach ($bonuses as $bonus) {
            if (strpos($bonus, '10%') !== false) {
                $finalScore *= 1.10;
            } elseif (strpos($bonus, '20%') !== false) {
                $finalScore *= 1.20;
            }
        }
        return $finalScore;
    }

    private function createOrUpdateOutcomeForApplication($application, $status, $reason = null, $averageScore = '0.00', $finalScore = '0.00')
    {
        ApplicationOutcome::updateOrCreate(
            ['application_id' => $application->id],
            [
                'status' => $status,
                'classification_status' => $status === 'approved' ? 'classifiable' : null,
                'average_score' => $averageScore,
                'final_score' => $finalScore,
                'reason' => $reason,
            ]
        );
    }

    private function normalizeString($string)
    {
        $string = trim($string);
        $string = preg_replace('/\s+/', '', $string);
        $string = strtolower($string);
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        return $string;
    }
}
