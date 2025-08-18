<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SiteSetting;

class AboutPageSeeder extends Seeder
{
    public function run(): void
    {
        $aboutSettings = [
            [
                'key' => 'about.hero_title',
                'value' => 'Sobre o Trust-me',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.hero_subtitle',
                'value' => 'Somos uma empresa dedicada a transformar a forma como as equipes colaboram e gerenciam seus projetos, oferecendo soluções inovadoras e confiáveis.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.mission_title',
                'value' => 'Nossa Missão',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.mission_text',
                'value' => 'Capacitar empresas de todos os tamanhos com ferramentas intuitivas e poderosas que simplificam a gestão de projetos e potencializam a colaboração em equipe.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.mission_text_2',
                'value' => 'Acreditamos que a tecnologia deve ser um facilitador, não um obstáculo. Por isso, desenvolvemos soluções que são ao mesmo tempo sofisticadas e fáceis de usar.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.values_title',
                'value' => 'Nossos Valores',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_1_title',
                'value' => 'Consentimento',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_1_description',
                'value' => 'Base de todos os relacionamentos saudáveis. Acreditamos que o consentimento explícito e documentado é fundamental para relações respeitosas.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_2_title',
                'value' => 'Segurança Jurídica',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_2_description',
                'value' => 'Proteção legal para ambas as partes. Garantimos que os acordos sejam válidos juridicamente e protejam os direitos de todos os envolvidos.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_3_title',
                'value' => 'Transparência',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_3_description',
                'value' => 'Clareza total em limites, preferências e responsabilidades. Eliminamos mal-entendidos através de comunicação clara e documentada.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_4_title',
                'value' => 'Respeito',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_4_description',
                'value' => 'Valorização da autonomia e escolhas individuais. Respeitamos todas as formas de relacionamento e orientações sexuais.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_5_title',
                'value' => 'Privacidade',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.value_5_description',
                'value' => 'Proteção absoluta de informações pessoais. Garantimos confidencialidade total em todos os acordos e dados.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.team_title',
                'value' => 'Nossa Equipe',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.team_subtitle',
                'value' => 'Profissionais apaixonados por tecnologia e dedicados a criar as melhores soluções para nossos clientes.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.team_text',
                'value' => 'Nossa equipe é composta por profissionais experientes e apaixonados por tecnologia. Trabalhamos com metodologias ágeis, sempre focados na qualidade e na experiência do usuário. Cada membro da nossa equipe contribui com sua expertise única para criar soluções inovadoras e eficientes.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.history_title',
                'value' => 'Nossa História',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.cta_title',
                'value' => 'Faça parte da nossa história',
                'type' => 'text',
                'group' => 'about',
            ],
            [
                'key' => 'about.cta_subtitle',
                'value' => 'Junte-se a milhares de empresas que já confiam no Trust-me para gerenciar seus projetos e alcançar seus objetivos.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.cta_button',
                'value' => 'Começar Agora',
                'type' => 'text',
                'group' => 'about',
            ],
        ];

        foreach ($aboutSettings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Configurações da página Sobre criadas/atualizadas com sucesso!');
    }
} 