<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Contact;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'pending_contacts' => Contact::where('status', 'pending')->count(),
        ];

        $recent_users = User::orderBy('created_at', 'desc')->take(5)->get();
        $pending_contacts = Contact::pending()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_users' => $recent_users,
                'pending_contacts' => $pending_contacts,
            ]
        ]);
    }

    public function users(Request $request)
    {
        $users = User::when($request->search, function($query, $search) {
                return $query->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->role, function($query, $role) {
                return $query->where('role', $role);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function contacts(Request $request)
    {
        $contacts = Contact::when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }
}
