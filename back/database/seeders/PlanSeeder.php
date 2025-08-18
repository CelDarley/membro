<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'description' => 'Plano ideal para pequenos negócios que estão começando',
                'monthly_price' => 29.90,
                'semiannual_price' => 99.90,
                'annual_price' => 199.90,
                'seals_limit' => 1,
                'contracts_limit' => 1,
                'features' => [
                    '1 selo digital',
                    '1 contrato',
                    'Suporte por email',
                    'Certificado SSL',
                    'Backup automático'
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Intermediário',
                'description' => 'Plano perfeito para empresas em crescimento',
                'monthly_price' => 49.90,
                'semiannual_price' => 199.90,
                'annual_price' => 299.90,
                'seals_limit' => 3,
                'contracts_limit' => 3,
                'features' => [
                    '3 selos digitais',
                    '3 contratos',
                    'Suporte prioritário',
                    'Certificado SSL',
                    'Backup automático',
                    'Relatórios avançados'
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Plus',
                'description' => 'Plano completo para empresas que precisam de recursos ilimitados',
                'monthly_price' => 69.90,
                'semiannual_price' => 299.90,
                'annual_price' => 499.90,
                'seals_limit' => null, // ilimitado
                'contracts_limit' => null, // ilimitado
                'features' => [
                    'Selos digitais ilimitados',
                    'Contratos ilimitados',
                    'Suporte 24/7',
                    'Certificado SSL',
                    'Backup automático',
                    'Relatórios avançados',
                    'API personalizada',
                    'Integração com sistemas'
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
