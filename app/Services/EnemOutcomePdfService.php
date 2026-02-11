<?php

namespace App\Services;

use App\Models\ProcessSelection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnemOutcomePdfService
{
    /**
     * Gera PDF com a lista de deferidos e indeferidos.
     *
     * @param  ProcessSelection  $selection
     * @return Response
     *
     * @throws \Exception se ainda houver pendentes
     */
    public function export(ProcessSelection $selection): Response
    {
        // Aumenta limite de memória para evitar erro do DomPDF
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        // ou para ilimitado:
        // ini_set('memory_limit', '-1');

        // 1) Verifica pendentes
        $hasPending = $selection
            ->applications()
            ->whereHas('applicationOutcome', fn($q) => $q->where('status', 'pending'))
            ->exists();

        if ($hasPending) {
            throw new \Exception('Ainda tem inscrição pendente.');
        }

        // 2) Monta lista só com aplicações que têm outcome
        $lista = $selection
            ->applications()
            ->with(['applicationOutcome', 'user'])
            ->whereHas('applicationOutcome')
            ->get()
            ->map(fn($app) => [
                'nome'   => $app->form_data['social_name']
                    ?? $app->form_data['name']
                    ?? $app->user->name,
                'cpf'    => $this->maskCpf($app->user->cpf),
                'status' => $app->applicationOutcome?->status === 'approved'
                    ? 'Deferido'
                    : 'Indeferido',
                'motivo' => $app->applicationOutcome?->status === 'rejected'
                    ? $app->applicationOutcome->reason
                    : '',
            ])
            ->sortBy(fn($item) => mb_strtolower($item['nome']))
            ->values();

        // 3) Renderiza PDF
        $filename = Str::slug($selection->name) . '-resultados.pdf';
        $pdf = Pdf::loadView('pdf.enem_outcomes_list', [
            'selection' => $selection,
            'lista'     => $lista,
        ]);

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Mascaramento de CPF no formato ***.123.456-**
     */
    private function maskCpf(string $cpf): string
    {
        $clean = preg_replace('/\D/', '', $cpf);
        return '***.' . substr($clean, 3, 3) . '.' . substr($clean, 6, 3) . '-**';
    }
}
