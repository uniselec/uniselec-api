<?php

namespace App\Services;

use App\Models\ProcessSelection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessSelectionApplicationCsvService
{
    /**
     * Exporta CSV com uma linha para cada categoria de inscrição do candidato.
     *
     * @param ProcessSelection $selection
     * @return StreamedResponse
     */
    public function export(ProcessSelection $selection): StreamedResponse
    {
        $filename = "inscricoes_process_{$selection->id}.csv";


        return response()->streamDownload(function () use ($selection) {
            $handle = fopen('php://output', 'w');


            // Cabeçalho CSV
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


            $selection->applications()
                ->with('user')
                ->orderBy('id')
                ->chunk(100, function ($apps) use ($handle) {
                    foreach ($apps as $app) {
                        $user = $app->user;
                        $data = $app->form_data;
                        $position = $data['position'] ?? [];
                        $courseName = $position['name'] ?? '';
                        $campus = $position['academic_unit']['name'] ?? '';
                        $enemNumber = $data['enem'] ?? '';
                        $enemYear = $data['enem_year'] ?? '';
                        $bonus = $data['bonus']['name'] ?? '';


                        // Para cada categoria de ingresso, gerar uma linha
                        foreach ($data['admission_categories'] as $cat) {
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
                                $app->created_at->toDateTimeString(),
                            ]);
                        }
                    }
                });


            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
