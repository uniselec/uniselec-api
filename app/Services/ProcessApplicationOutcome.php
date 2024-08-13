<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationOutcome;
use App\Models\EnemScore;
use Illuminate\Support\Collection;

class ProcessApplicationOutcome
{
    public function process()
    {
        $this->ensureAllApplicationsHaveOutcomes();
        $this->processEnemScores();
        $this->assignRanking();
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
            $averageScore = $this->calculateAverageScore($enemScore->scores);
            $finalScore = $this->applyBonus($averageScore, $application->data['bonus']);


            if ($enemScore->scores['cpf'] !== $application->data['cpf']) {
                $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Inconsistência no CPF', $averageScore, $finalScore);
                continue;
            }


            if ($this->normalizeString($enemScore->scores['name']) !== $this->normalizeString($application->data['name'])) {
                $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Inconsistência no Nome', $averageScore, $finalScore);
                continue;
            }


            if (isset($application->data['birtdate']) && isset($enemScore->scores['birthdate'])) {
                $applicationBirthdate = \DateTime::createFromFormat('Y-m-d', $application->data['birtdate']);
                $enemScoreBirthdate = \DateTime::createFromFormat('d/m/Y', $enemScore->scores['birthdate']);

                if (!$applicationBirthdate || !$enemScoreBirthdate || $applicationBirthdate->format('Y-m-d') !== $enemScoreBirthdate->format('Y-m-d')) {
                    $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Inconsistência na Data de Nascimento', $averageScore, $finalScore);
                    continue;
                }
            } else {

                $this->createOrUpdateOutcomeForApplication($application, 'pending', 'Data de Nascimento ausente ou inconsistente', $averageScore, $finalScore);
                continue;
            }


            $this->createOrUpdateOutcomeForApplication($application, 'approved', null, $averageScore, $finalScore);
        }
    }

    private function assignRanking()
    {

        $outcomes = ApplicationOutcome::whereIn('status', ['approved', 'pending'])
            ->with(['application' => function ($query) {
                $query->select('id', 'data');
            }])
            ->orderBy('final_score', 'desc')
            ->get();


        $outcomes = $outcomes->sort(function ($a, $b) {
            if ($a->final_score === $b->final_score) {
                $aBirthdate = \DateTime::createFromFormat('Y-m-d', $a->application->data['birtdate']);
                $bBirthdate = \DateTime::createFromFormat('Y-m-d', $b->application->data['birtdate']);


                if ($aBirthdate != $bBirthdate) {
                    return $aBirthdate < $bBirthdate ? -1 : 1;
                }


                if ($a->application->enem_score->scores['writing_score'] != $b->application->enem_score->scores['writing_score']) {
                    return $a->application->enem_score->scores['writing_score'] > $b->application->enem_score->scores['writing_score'] ? -1 : 1;
                }


                if ($a->application->enem_score->scores['language_score'] != $b->application->enem_score->scores['language_score']) {
                    return $a->application->enem_score->scores['language_score'] > $b->application->enem_score->scores['language_score'] ? -1 : 1;
                }


                if ($a->application->enem_score->scores['math_score'] != $b->application->enem_score->scores['math_score']) {
                    return $a->application->enem_score->scores['math_score'] > $b->application->enem_score->scores['math_score'] ? -1 : 1;
                }


                if ($a->application->enem_score->scores['science_score'] != $b->application->enem_score->scores['science_score']) {
                    return $a->application->enem_score->scores['science_score'] > $b->application->enem_score->scores['science_score'] ? -1 : 1;
                }


                if ($a->application->enem_score->scores['humanities_score'] != $b->application->enem_score->scores['humanities_score']) {
                    return $a->application->enem_score->scores['humanities_score'] > $b->application->enem_score->scores['humanities_score'] ? -1 : 1;
                }
            }

            return $a->final_score > $b->final_score ? -1 : 1;
        });


        foreach ($outcomes as $index => $outcome) {
            $outcome->ranking = $index + 1;
            $outcome->save();
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
