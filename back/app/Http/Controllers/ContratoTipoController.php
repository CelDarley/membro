<?php

namespace App\Http\Controllers;

use App\Models\ContratoTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratoTipoController extends Controller
{
    public function index(Request $request)
    {
        $query = ContratoTipo::query();

        // Filtro por código
        if ($request->has('codigo')) {
            $query->where('codigo', 'like', '%' . $request->codigo . '%');
        }

        // Filtro por descrição
        if ($request->has('descricao')) {
            $query->where('descricao', 'like', '%' . $request->descricao . '%');
        }

        $contratoTipos = $query->orderBy('codigo')->get();

        return response()->json([
            'success' => true,
            'data' => $contratoTipos
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|unique:contrato_tipos,codigo',
            'descricao' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contratoTipo = ContratoTipo::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de contrato criado com sucesso',
            'data' => $contratoTipo
        ], 201);
    }

    public function show($id)
    {
        $contratoTipo = ContratoTipo::find($id);

        if (!$contratoTipo) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de contrato não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $contratoTipo
        ]);
    }

    public function update(Request $request, $id)
    {
        $contratoTipo = ContratoTipo::find($id);

        if (!$contratoTipo) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de contrato não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|unique:contrato_tipos,codigo,' . $id,
            'descricao' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contratoTipo->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tipo de contrato atualizado com sucesso',
            'data' => $contratoTipo
        ]);
    }

    public function destroy($id)
    {
        $contratoTipo = ContratoTipo::find($id);

        if (!$contratoTipo) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de contrato não encontrado'
            ], 404);
        }

        $contratoTipo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tipo de contrato excluído com sucesso'
        ]);
    }
}
