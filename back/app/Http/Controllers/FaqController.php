<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::active()->ordered()->get();
        
        return response()->json([
            'success' => true,
            'data' => $faqs
        ]);
    }

    public function show($id)
    {
        $faq = Faq::find($id);
        
        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $faq
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $faq = Faq::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $faq,
            'message' => 'FAQ criado com sucesso'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $faq = Faq::find($id);
        
        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'question' => 'string|max:255',
            'answer' => 'string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $faq->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $faq,
            'message' => 'FAQ atualizado com sucesso'
        ]);
    }

    public function destroy($id)
    {
        $faq = Faq::find($id);
        
        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ não encontrado'
            ], 404);
        }

        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ excluído com sucesso'
        ]);
    }
}
