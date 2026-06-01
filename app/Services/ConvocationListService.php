<?php

namespace App\Services;

use App\Models\ConvocationList;

class ConvocationListService
{
    private const ALLOWED_TRANSITIONS = [
        'draft'     => ['published'],
        'published' => ['finalized'],
        'finalized' => [],
    ];

    private const TRANSITION_ERRORS = [
        'draft'     => [
            'finalized' => 'A lista precisa ser publicada antes de ser finalizada.',
        ],
        'published' => [
            'draft'     => 'Não é possível reverter uma lista publicada para rascunho.',
        ],
        'finalized' => [
            'draft'     => 'Listas finalizadas não podem ser alteradas.',
            'published' => 'Listas finalizadas não podem ser alteradas.',
        ],
    ];

    public function validateStatusTransition(ConvocationList $list, string $newStatus): ServiceResult
    {
        $allowed = self::ALLOWED_TRANSITIONS[$list->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            $message = self::TRANSITION_ERRORS[$list->status][$newStatus]
                ?? "Transição de '{$list->status}' para '{$newStatus}' não é permitida.";

            return ServiceResult::failure(message: $message);
        }

        if ($newStatus === 'finalized' && $list->seats()->where('status', 'reserved')->exists()) {
            return ServiceResult::failure(
                message: 'Há candidatos convocados com resposta em espera. Processe todas as respostas antes de finalizar a lista.'
            );
        }

        return ServiceResult::success();
    }
}
