<?php

namespace App\Http\Controllers;

use App\Models\Selo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeloController extends Controller
{
    public function index(Request $request)
    {
        $query = Selo::query();

        // Filtro por código
        if ($request->has('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        // Filtro por descrição
        if ($request->has('descricao')) {
            $query->where('descricao', 'like', '%' . $request->descricao . '%');
        }

        $selos = $query->orderBy('codigo')->get();

        return response()->json([
            'success' => true,
            'data' => $selos
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string',
            'descricao' => 'required|string',
            'validade' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $selo = Selo::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Selo criado com sucesso',
            'data' => $selo
        ], 201);
    }

    public function show($id)
    {
        $selo = Selo::find($id);

        if (!$selo) {
            return response()->json([
                'success' => false,
                'message' => 'Selo não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $selo
        ]);
    }

    public function update(Request $request, $id)
    {
        $selo = Selo::find($id);

        if (!$selo) {
            return response()->json([
                'success' => false,
                'message' => 'Selo não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string',
            'descricao' => 'required|string',
            'validade' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Atualiza diretamente o registro
        $selo->update([
            'codigo' => $request->codigo,
            'descricao' => $request->descricao,
            'validade' => $request->validade
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Selo atualizado com sucesso',
            'data' => $selo
        ]);
    }

    public function destroy($id)
    {
        $selo = Selo::find($id);

        if (!$selo) {
            return response()->json([
                'success' => false,
                'message' => 'Selo não encontrado'
            ], 404);
        }

        $selo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Selo excluído com sucesso'
        ]);
    }
}
