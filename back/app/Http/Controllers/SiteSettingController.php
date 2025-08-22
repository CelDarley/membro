<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SiteSettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = SiteSetting::when($request->group, function($query, $group) {
                return $query->where('group', $group);
            })
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function show($key)
    {
        $setting = SiteSetting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Configuração não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|unique:site_settings,key',
            'value' => 'nullable|string',
            'type' => 'in:text,textarea,boolean,json',
            'group' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $setting = SiteSetting::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $setting,
            'message' => 'Configuração criada com sucesso'
        ], 201);
    }

    public function update(Request $request, $key)
    {
        $setting = SiteSetting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Configuração não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'nullable|string',
            'type' => 'in:text,textarea,boolean,json',
            'group' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $setting->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $setting,
            'message' => 'Configuração atualizada com sucesso'
        ]);
    }

    public function destroy($key)
    {
        $setting = SiteSetting::where('key', $key)->first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Configuração não encontrada'
            ], 404);
        }

        $setting->delete();

        return response()->json([
            'success' => true,
            'message' => 'Configuração excluída com sucesso'
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->settings as $settingData) {
            SiteSetting::updateOrCreate(
                ['key' => $settingData['key']],
                ['value' => $settingData['value']]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Configurações atualizadas com sucesso'
        ]);
    }

    public function getSiteContent()
    {
        // Buscar todas as configurações do site
        $settings = SiteSetting::all()->keyBy('key');
        
        // Buscar depoimentos ativos
        $testimonials = \App\Models\Testimonial::where('is_active', true)->get();
        
        // Buscar planos ativos
        $plans = \App\Models\Plan::where('is_active', true)->get();
        
        // Buscar FAQs ativas
        $faqs = \App\Models\Faq::where('is_active', true)->get();

        // Estruturar o conteúdo do site
        $content = [
            // Configurações gerais
            'site_name' => $settings->get('site_name')?->value ?? 'Trust-me',
            'site_slogan' => $settings->get('site_slogan')?->value ?? 'Plataforma de Certificação Digital',
            'site_description' => $settings->get('site_description')?->value ?? 'Plataforma de certificação digital e contratos seguros',
            
            // Hero Section
            'home.hero_title' => $settings->get('home.hero_title')?->value ?? 'Certificação Digital e Contratos Seguros',
            'home.hero_subtitle' => $settings->get('home.hero_subtitle')?->value ?? 'Aumente a confiança dos seus clientes com nossos selos digitais e contratos seguros',
            'home.cta_primary_label' => $settings->get('home.cta_primary_label')?->value ?? 'Começar Agora',
            'home.cta_secondary_label' => $settings->get('home.cta_secondary_label')?->value ?? 'Ver Planos',
            
            // Features Section
            'home.features' => [
                [
                    'title' => 'Certificação Digital',
                    'text' => 'Selo de confiança para aumentar a credibilidade da sua empresa'
                ],
                [
                    'title' => 'Contratos Seguros',
                    'text' => 'Crie e gerencie contratos digitais com assinatura eletrônica'
                ],
                [
                    'title' => 'Suporte Especializado',
                    'text' => 'Equipe técnica pronta para ajudar em qualquer momento'
                ]
            ],
            
            // Stats Section
            'home.stats' => [
                ['value' => '1000+', 'label' => 'Clientes Atendidos'],
                ['value' => '5000+', 'label' => 'Documentos Certificados'],
                ['value' => '99.9%', 'label' => 'Uptime'],
                ['value' => '24/7', 'label' => 'Suporte']
            ],
            
            // Steps Section
            'home.steps' => [
                [
                    'title' => 'Cadastre-se',
                    'text' => 'Crie sua conta gratuitamente em menos de 2 minutos'
                ],
                [
                    'title' => 'Escolha seu Plano',
                    'text' => 'Selecione o plano que melhor atende suas necessidades'
                ],
                [
                    'title' => 'Comece a Usar',
                    'text' => 'Acesse todas as funcionalidades imediatamente'
                ]
            ],
            
            // CTA Section
            'home.cta_block_title' => $settings->get('home.cta_block_title')?->value ?? 'Pronto para Começar?',
            'home.cta_block_subtitle' => $settings->get('home.cta_block_subtitle')?->value ?? 'Junte-se a milhares de empresas que já confiam no Trust-me',
            
            // Configurações da página Sobre
            'about.hero_title' => $settings->get('about.hero_title')?->value ?? 'Sobre o Consentir',
            'about.hero_subtitle' => $settings->get('about.hero_subtitle')?->value ?? 'Somos uma empresa dedicada a transformar a forma como as equipes colaboram e gerenciam seus projetos, oferecendo soluções inovadoras e confiáveis.',
            'about.mission_title' => $settings->get('about.mission_title')?->value ?? 'Nossa Missão',
            'about.mission_text' => $settings->get('about.mission_text')?->value ?? 'Capacitar empresas de todos os tamanhos com ferramentas intuitivas e poderosas que simplificam a gestão de projetos e potencializam a colaboração em equipe.',
            'about.mission_text_2' => $settings->get('about.mission_text_2')?->value ?? 'Acreditamos que a tecnologia deve ser um facilitador, não um obstáculo. Por isso, desenvolvemos soluções que são ao mesmo tempo sofisticadas e fáceis de usar.',
            'about.values_title' => $settings->get('about.values_title')?->value ?? 'Nossos Valores',
            'about.value_1_title' => $settings->get('about.value_1_title')?->value ?? 'Consentimento',
            'about.value_1_description' => $settings->get('about.value_1_description')?->value ?? 'Base de todos os relacionamentos saudáveis. Acreditamos que o consentimento explícito e documentado é fundamental para relações respeitosas.',
            'about.value_2_title' => $settings->get('about.value_2_title')?->value ?? 'Segurança Jurídica',
            'about.value_2_description' => $settings->get('about.value_2_description')?->value ?? 'Proteção legal para ambas as partes. Garantimos que os acordos sejam válidos juridicamente e protejam os direitos de todos os envolvidos.',
            'about.value_3_title' => $settings->get('about.value_3_title')?->value ?? 'Transparência',
            'about.value_3_description' => $settings->get('about.value_3_description')?->value ?? 'Clareza total em limites, preferências e responsabilidades. Eliminamos mal-entendidos através de comunicação clara e documentada.',
            'about.value_4_title' => $settings->get('about.value_4_title')?->value ?? 'Respeito',
            'about.value_4_description' => $settings->get('about.value_4_description')?->value ?? 'Valorização da autonomia e escolhas individuais. Respeitamos todas as formas de relacionamento e orientações sexuais.',
            'about.value_5_title' => $settings->get('about.value_5_title')?->value ?? 'Privacidade',
            'about.value_5_description' => $settings->get('about.value_5_description')?->value ?? 'Proteção absoluta de informações pessoais. Garantimos confidencialidade total em todos os acordos e dados.',
            'about.team_title' => $settings->get('about.team_title')?->value ?? 'Nossa Equipe',
            'about.team_subtitle' => $settings->get('about.team_subtitle')?->value ?? 'Profissionais apaixonados por tecnologia e dedicados a criar as melhores soluções para nossos clientes.',
            'about.team_text' => $settings->get('about.team_text')?->value ?? 'Nossa equipe é composta por profissionais experientes e apaixonados por tecnologia.',
            'about.history_title' => $settings->get('about.history_title')?->value ?? 'Nossa História',
            'about.history_text' => $settings->get('about.history_text')?->value ?? 'Nossa jornada começou em 2020 com uma visão clara: transformar a forma como as empresas gerenciam projetos e colaboram em equipe.',
            'about.cta_title' => $settings->get('about.cta_title')?->value ?? 'Faça parte da nossa história',
            'about.cta_subtitle' => $settings->get('about.cta_subtitle')?->value ?? 'Junte-se a milhares de empresas que já confiam no Consentir para gerenciar seus projetos e alcançar seus objetivos.',
            'about.cta_button' => $settings->get('about.cta_button')?->value ?? 'Começar Agora',
            
            // Dados dinâmicos
            'testimonials' => $testimonials,
            'plans' => $plans,
            'faqs' => $faqs,
            
            // Informações de contato
            'contact_email' => $settings->get('contact_email')?->value ?? 'contato@trustme.com',
            'contact_phone' => $settings->get('contact_phone')?->value ?? '(11) 99999-9999',
            'contact_address' => $settings->get('contact_address')?->value ?? 'Rua das Empresas, 123 - São Paulo, SP'
        ];

        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }
}
