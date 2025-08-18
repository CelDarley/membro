<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Selo;

class SeloSeeder extends Seeder
{
    public function run(): void
    {
        $selos = [
            [
                'codigo' => 'SELO001',
                'descricao' => 'Selo de Qualidade',
                'validade' => 365
            ],
            [
                'codigo' => 'SELO002',
                'descricao' => 'Selo de Segurança',
                'validade' => 180
            ],
            [
                'codigo' => 'SELO003',
                'descricao' => 'Selo de Conformidade',
                'validade' => 90
            ],
            [
                'codigo' => 'SELO004',
                'descricao' => 'Selo de Excelência',
                'validade' => 730
            ],
            [
                'codigo' => 'SELO005',
                'descricao' => 'Selo de Inovação',
                'validade' => 365
            ]
        ];

        foreach ($selos as $selo) {
            Selo::create($selo);
        }
    }
}
