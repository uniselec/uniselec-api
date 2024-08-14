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
            $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Resultado Não Processado');
        }
    }

    private function processEnemScores()
    {
        $enemScores = EnemScore::with('application')->get();
        $processedApplicationIds = [];

        foreach ($enemScores as $enemScore) {
            $application = $enemScore->application;
            $processedApplicationIds[] = $application->id;

            $averageScore = $this->calculateAverageScore($enemScore->scores);
            $finalScore = $this->applyBonus($averageScore, $application->data['bonus']);

            $reasons = [];

            // Validação de CPF
            if ($enemScore->scores['cpf'] !== $application->data['cpf']) {
                $reasons[] = 'Inconsistência no CPF';
            }

            // Validação de Nome
            if ($this->normalizeString($enemScore->scores['name']) !== $this->normalizeString($application->data['name'])) {
                $reasons[] = 'Inconsistência no Nome';
            }

            // Validação de Data de Nascimento
            if (isset($application->data['birtdate']) && isset($enemScore->scores['birthdate'])) {
                $applicationBirthdate = \DateTime::createFromFormat('Y-m-d', $application->data['birtdate']);
                $enemScoreBirthdate = \DateTime::createFromFormat('d/m/Y', $enemScore->scores['birthdate']);

                if (!$applicationBirthdate || !$enemScoreBirthdate || $applicationBirthdate->format('Y-m-d') !== $enemScoreBirthdate->format('Y-m-d')) {
                    $reasons[] = 'Inconsistência na Data de Nascimento';
                }
            } else {
                $reasons[] = 'Data de Nascimento ausente ou inconsistente';
            }

            // Se houverem inconsistências, cria um resultado pendente
            if (!empty($reasons)) {
                $this->createOrUpdateOutcomeForApplication($application, 'pending', implode('; ', $reasons), $averageScore, $finalScore);
            } else {
                // Se não houverem inconsistências, aprova a aplicação
                $this->createOrUpdateOutcomeForApplication($application, 'approved', null, $averageScore, $finalScore);
            }
        }

        // Verificar se há aplicações sem EnemScore associado e marcar como pendentes
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
