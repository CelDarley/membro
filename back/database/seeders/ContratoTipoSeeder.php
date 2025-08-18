<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContratoTipo;

class ContratoTipoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'codigo' => 'CT001',
                'descricao' => 'Contrato de Prestação de Serviços'
            ],
            [
                'codigo' => 'CT002',
                'descricao' => 'Contrato de Trabalho'
            ],
            [
                'codigo' => 'CT003',
                'descricao' => 'Contrato de Compra e Venda'
            ],
            [
                'codigo' => 'CT004',
                'descricao' => 'Contrato de Locação'
            ],
            [
                'codigo' => 'CT005',
                'descricao' => 'Contrato de Parceria'
            ]
        ];

        foreach ($tipos as $tipo) {
            ContratoTipo::create($tipo);
        }
    }
}
