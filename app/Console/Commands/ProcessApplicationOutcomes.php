<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProcessApplicationOutcome;

class ProcessApplicationOutcomes extends Command
{
    protected $signature = 'process:outcomes {selectionId}';

    protected $description = 'Processa os outcomes de um processo seletivo';

    public function handle()
    {
        $selectionId = (int) $this->argument('selectionId');
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        $this->info("Processando outcomes do processo {$selectionId}...");
        (new ProcessApplicationOutcome($selectionId))->process();
        $this->info("Resultados processados com sucesso!");

        return Command::SUCCESS;
    }
}
