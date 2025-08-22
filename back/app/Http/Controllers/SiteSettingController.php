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
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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
        
        // Estruturar o conteúdo do site (somente settings e textos estáticos)
        $content = [
            'site_name' => $settings->get('site_name')?->value ?? 'Membro',
            'site_slogan' => $settings->get('site_slogan')?->value ?? '',
            'site_description' => $settings->get('site_description')?->value ?? '',

            // Blocos de exemplo (mantidos estáticos/por settings se existirem)
            'home.hero_title' => $settings->get('home.hero_title')?->value ?? 'Bem-vindo',
            'home.hero_subtitle' => $settings->get('home.hero_subtitle')?->value ?? '',
            'home.cta_primary_label' => $settings->get('home.cta_primary_label')?->value ?? 'Entrar',
            'home.cta_secondary_label' => $settings->get('home.cta_secondary_label')?->value ?? 'Saiba mais',
        ];

        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }
}
