<?php
namespace App\Services;

use App\Models\ConvocationList;
use App\Models\ConvocationListSeat;
use App\Models\ConvocationListApplication;
use Illuminate\Support\Facades\DB;

class SeatRedistributionService
{
    /**
     * Preenche vagas até acabar inscrição elegível
     * ou não restarem mais assentos livres/remanejáveis.
     *
     * @return int  quantidade de assentos efetivamente preenchidos
     */
    public function redistribute(ConvocationList $list): int
    {


        return 0;
    }
}
