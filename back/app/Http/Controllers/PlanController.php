<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    public function show($id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plano não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'monthly_price' => 'required|numeric|min:0',
            'semiannual_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'seals_limit' => 'nullable|integer|min:0',
            'contracts_limit' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan = Plan::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plano criado com sucesso'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plano não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string',
            'monthly_price' => 'numeric|min:0',
            'semiannual_price' => 'numeric|min:0',
            'annual_price' => 'numeric|min:0',
            'seals_limit' => 'nullable|integer|min:0',
            'contracts_limit' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plano atualizado com sucesso'
        ]);
    }

    public function destroy($id)
    {
        $plan = Plan::find($id);
        
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plano não encontrado'
            ], 404);
        }

        $plan->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Plano desativado com sucesso'
        ]);
    }
}
