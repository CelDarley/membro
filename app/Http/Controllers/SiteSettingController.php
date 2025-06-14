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
}
