<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'O que é o Trust-me?',
                'answer' => 'O Trust-me é uma plataforma de certificação digital que oferece selos de confiança e contratos digitais seguros para empresas de todos os tamanhos.',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'Como funciona o selo digital?',
                'answer' => 'O selo digital é um certificado que comprova a autenticidade e segurança do seu site ou aplicação, aumentando a confiança dos seus clientes.',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'Posso cancelar minha assinatura a qualquer momento?',
                'answer' => 'Sim, você pode cancelar sua assinatura a qualquer momento através do painel administrativo ou entrando em contato conosco.',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'question' => 'Qual a diferença entre os planos?',
                'answer' => 'Os planos diferem na quantidade de selos e contratos inclusos, além dos recursos disponíveis. O plano Básico oferece 1 selo e 1 contrato, o Intermediário oferece 3 de cada, e o Plus oferece recursos ilimitados.',
                'order' => 4,
                'is_active' => true,
            ],
            [
                'question' => 'Como posso integrar o Trust-me ao meu sistema?',
                'answer' => 'Oferecemos APIs e documentação completa para integração. Clientes do plano Plus têm acesso a APIs personalizadas e suporte técnico especializado.',
                'order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}
