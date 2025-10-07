<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use Hyperf\DbConnection\Db;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $id = '1be2b2f8-aaaa-bbbb-cccc-123456789abc'; // UUID fixo para testar via curl

        // Apaga se já existir (idempotente)
        Db::table('accounts')->where('id', $id)->delete();

        Db::table('accounts')->insert([
            'id' => $id,
            'balance' => 500.00,
            'version' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo "Conta criada com ID {$id} e saldo R$ 500.00\n";
    }
}
