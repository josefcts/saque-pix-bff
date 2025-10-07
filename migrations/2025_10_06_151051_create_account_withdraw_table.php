<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_withdraw', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id');
            $t->string('method', 32);
            $t->decimal('amount', 14, 2);
            $t->unsignedBigInteger('version')->default(1);

            // horário em que o saque deve ser processado (sempre UTC)
            $t->dateTime('scheduled_for')->nullable();

            // horário em que foi enfileirado pelo cron (idempotência)
            $t->dateTime('queued_at')->nullable();

            // flags de status
            $t->boolean('done')->default(false);
            $t->boolean('error')->default(false);

            // erro opcional
            $t->string('error_code', 64)->nullable();

            // request de origem (traceability)
            $t->string('request_id', 64)->nullable();

            $t->timestamps();

            // relacionamento
            $t->foreign('account_id')->references('id')->on('accounts');

            // índice otimizado para o cron scanner
            $t->index(['done', 'error', 'queued_at', 'scheduled_for'], 'idx_withdraw_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_withdraw');
    }
};
