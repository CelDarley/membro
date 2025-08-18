<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Testimonial;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'João Silva',
                'company' => 'TechCorp Ltda',
                'content' => 'O Trust-me revolucionou nossa forma de trabalhar com contratos digitais. A segurança e praticidade são incomparáveis.',
                'avatar' => null,
                'rating' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Maria Santos',
                'company' => 'Inovação Digital',
                'content' => 'Excelente plataforma! Nossos clientes confiam mais em nossos serviços desde que implementamos o selo Trust-me.',
                'avatar' => null,
                'rating' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Pedro Oliveira',
                'company' => 'StartupXYZ',
                'content' => 'Como startup, precisávamos de uma solução confiável e acessível. O Trust-me atendeu todas nossas expectativas.',
                'avatar' => null,
                'rating' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Ana Costa',
                'company' => 'E-commerce Plus',
                'content' => 'A implementação foi muito simples e o suporte é excepcional. Recomendo para qualquer empresa que valoriza a segurança digital.',
                'avatar' => null,
                'rating' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::create($testimonial);
        }
    }
}
