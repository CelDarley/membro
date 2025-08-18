<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Contact;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_plans' => Plan::where('is_active', true)->count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'pending_contacts' => Contact::where('status', 'pending')->count(),
            'monthly_revenue' => Subscription::where('status', 'active')
                ->where('billing_cycle', 'monthly')
                ->sum('amount'),
            'total_revenue' => Subscription::where('status', 'active')->sum('amount'),
        ];

        $recent_users = User::orderBy('created_at', 'desc')->take(5)->get();
        $recent_subscriptions = Subscription::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        $pending_contacts = Contact::pending()
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_users' => $recent_users,
                'recent_subscriptions' => $recent_subscriptions,
                'pending_contacts' => $pending_contacts,
            ]
        ]);
    }

    public function users(Request $request)
    {
        $users = User::with(['subscriptions.plan'])
            ->when($request->search, function($query, $search) {
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

    public function subscriptions(Request $request)
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->plan_id, function($query, $planId) {
                return $query->where('plan_id', $planId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $subscriptions
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

    public function reports()
    {
        $monthlyStats = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyStats[] = [
                'month' => $date->format('Y-m'),
                'users' => User::whereYear('created_at', $date->year)
                             ->whereMonth('created_at', $date->month)
                             ->count(),
                'subscriptions' => Subscription::whereYear('created_at', $date->year)
                                              ->whereMonth('created_at', $date->month)
                                              ->count(),
                'revenue' => Subscription::whereYear('created_at', $date->year)
                                        ->whereMonth('created_at', $date->month)
                                        ->where('status', 'active')
                                        ->sum('amount'),
            ];
        }

        $planStats = Plan::withCount(['subscriptions' => function($query) {
            $query->where('status', 'active');
        }])->get();

        return response()->json([
            'success' => true,
            'data' => [
                'monthly_stats' => $monthlyStats,
                'plan_stats' => $planStats,
            ]
        ]);
    }
}
