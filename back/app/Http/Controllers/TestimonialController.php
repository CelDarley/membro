<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::active()->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $testimonials
        ]);
    }

    public function show($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Depoimento não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $testimonial
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'content' => 'required|string',
            'avatar' => 'nullable|string',
            'rating' => 'integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $testimonial = Testimonial::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $testimonial,
            'message' => 'Depoimento criado com sucesso'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Depoimento não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'company' => 'nullable|string|max:255',
            'content' => 'string',
            'avatar' => 'nullable|string',
            'rating' => 'integer|min:1|max:5',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $testimonial->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $testimonial,
            'message' => 'Depoimento atualizado com sucesso'
        ]);
    }

    public function destroy($id)
    {
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            return response()->json([
                'success' => false,
                'message' => 'Depoimento não encontrado'
            ], 404);
        }

        $testimonial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Depoimento excluído com sucesso'
        ]);
    }
}
