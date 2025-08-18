<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SiteSetting;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Configurações gerais
            [
                'key' => 'site_name',
                'value' => 'Trust-me',
                'type' => 'text',
                'group' => 'general',
            ],
            [
                'key' => 'site_slogan',
                'value' => 'Plataforma de Certificação Digital',
                'type' => 'text',
                'group' => 'general',
            ],
            [
                'key' => 'site_description',
                'value' => 'Plataforma de certificação digital e contratos seguros',
                'type' => 'textarea',
                'group' => 'general',
            ],
            
            // Configurações da tela Home
            [
                'key' => 'home.hero_title',
                'value' => 'Certificação Digital e Contratos Seguros',
                'type' => 'text',
                'group' => 'home',
            ],
            [
                'key' => 'home.hero_subtitle',
                'value' => 'Aumente a confiança dos seus clientes com nossos selos digitais e contratos seguros',
                'type' => 'textarea',
                'group' => 'home',
            ],
            [
                'key' => 'home.cta_primary_label',
                'value' => 'Começar Agora',
                'type' => 'text',
                'group' => 'home',
            ],
            [
                'key' => 'home.cta_secondary_label',
                'value' => 'Ver Planos',
                'type' => 'text',
                'group' => 'home',
            ],
            [
                'key' => 'home.cta_block_title',
                'value' => 'Pronto para Começar?',
                'type' => 'text',
                'group' => 'home',
            ],
            [
                'key' => 'home.cta_block_subtitle',
                'value' => 'Junte-se a milhares de empresas que já confiam no Trust-me',
                'type' => 'textarea',
                'group' => 'home',
            ],
            
            // Configurações da página Sobre
            [
                'key' => 'about.hero_title',
                'value' => 'Sobre o Consentir',
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
                'value' => 'Junte-se a milhares de empresas que já confiam no Consentir para gerenciar seus projetos e alcançar seus objetivos.',
                'type' => 'textarea',
                'group' => 'about',
            ],
            [
                'key' => 'about.cta_button',
                'value' => 'Começar Agora',
                'type' => 'text',
                'group' => 'about',
            ],
            
            // Configurações de contato
            [
                'key' => 'contact_email',
                'value' => 'contato@trustme.com',
                'type' => 'text',
                'group' => 'contact',
            ],
            [
                'key' => 'contact_phone',
                'value' => '(11) 99999-9999',
                'type' => 'text',
                'group' => 'contact',
            ],
            [
                'key' => 'contact_address',
                'value' => 'Rua das Empresas, 123 - São Paulo, SP',
                'type' => 'textarea',
                'group' => 'contact',
            ],
            
            // Configurações de pagamento
            [
                'key' => 'mercado_pago_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'payment',
            ],
            [
                'key' => 'mercado_pago_public_key',
                'value' => '',
                'type' => 'text',
                'group' => 'payment',
            ],
            [
                'key' => 'mercado_pago_access_token',
                'value' => '',
                'type' => 'text',
                'group' => 'payment',
            ],
            
            // Configurações de email
            [
                'key' => 'email_notifications_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'email',
            ],
            [
                'key' => 'welcome_email_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'email',
            ],
            
            // Configurações de SEO
            [
                'key' => 'meta_keywords',
                'value' => 'certificação digital, contratos digitais, selo de confiança, segurança digital',
                'type' => 'textarea',
                'group' => 'seo',
            ],
            [
                'key' => 'meta_description',
                'value' => 'Trust-me oferece certificação digital e contratos seguros para empresas. Aumente a confiança dos seus clientes com nossos selos digitais.',
                'type' => 'textarea',
                'group' => 'seo',
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::create($setting);
        }
    }
}
