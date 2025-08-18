<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = Contact::when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }

    public function show($id)
    {
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contato não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $contact
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contact = Contact::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $contact,
            'message' => 'Mensagem enviada com sucesso'
        ], 201);
    }

    public function respond(Request $request, $id)
    {
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contato não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'admin_response' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contact->markAsAnswered($request->admin_response);

        return response()->json([
            'success' => true,
            'data' => $contact,
            'message' => 'Resposta enviada com sucesso'
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contato não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,answered,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contact->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'data' => $contact,
            'message' => 'Status atualizado com sucesso'
        ]);
    }

    public function destroy($id)
    {
        $contact = Contact::find($id);
        
        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contato não encontrado'
            ], 404);
        }

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contato excluído com sucesso'
        ]);
    }
}
