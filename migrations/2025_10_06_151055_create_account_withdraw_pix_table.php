<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('account_withdraw_pix', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_withdraw_id');
            $t->uuid('account_id')->nullable();
            $t->string('type', 16);        // tipo da chave PIX
            $t->string('key', 255);        // valor da chave PIX
            $t->string('status', 32)->default('CREATED');
            $t->string('provider', 64)->nullable();
            $t->string('tid', 128)->nullable();
            $t->string('error_code', 64)->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();

            $t->foreign('account_withdraw_id')->references('id')->on('account_withdraw');
            $t->index(['status', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_withdraw_pix');
    }
};
