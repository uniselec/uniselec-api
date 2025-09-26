<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'convocation_list_seats';
    private string $column = 'status';

    /** @noinspection SqlWithoutWhere */
    public function up(): void
    {
        // ▸ 1) Adiciona temporariamente um novo ENUM com 'unfilled'
        DB::statement("
            ALTER TABLE {$this->table}
            MODIFY {$this->column}
            ENUM('open','reserved','filled','unfilled')
            NOT NULL DEFAULT 'open'
        ");
    }

    /** @noinspection SqlWithoutWhere */
    public function down(): void
    {
        // ▸ 2) Reverte para o ENUM original (sem 'unfilled')
        //     – antes, converte possíveis valores 'unfilled' de volta para 'open'
        DB::table($this->table)
            ->where($this->column, 'unfilled')
            ->update([$this->column => 'open']);

        DB::statement("
            ALTER TABLE {$this->table}
            MODIFY {$this->column}
            ENUM('open','reserved','filled')
            NOT NULL DEFAULT 'open'
        ");
    }
};

