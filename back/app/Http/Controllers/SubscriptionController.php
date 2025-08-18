<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($request->user_id, function($query, $userId) {
                return $query->where('user_id', $userId);
            })
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }

    public function show($id)
    {
        $subscription = Subscription::with(['user', 'plan'])->find($id);
        
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,semiannual,annual',
            'payment_method' => 'nullable|string',
            'payment_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan = Plan::find($request->plan_id);
        $amount = $plan->getPriceForCycle($request->billing_cycle);
        
        $startDate = Carbon::now();
        $endDate = match($request->billing_cycle) {
            'monthly' => $startDate->copy()->addMonth(),
            'semiannual' => $startDate->copy()->addMonths(6),
            'annual' => $startDate->copy()->addYear(),
        };

        $subscription = Subscription::create([
            'user_id' => $request->user_id,
            'plan_id' => $request->plan_id,
            'billing_cycle' => $request->billing_cycle,
            'amount' => $amount,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_method' => $request->payment_method,
            'payment_id' => $request->payment_id,
            'payment_data' => $request->payment_data,
        ]);

        return response()->json([
            'success' => true,
            'data' => $subscription->load(['user', 'plan']),
            'message' => 'Assinatura criada com sucesso'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $subscription = Subscription::find($id);
        
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'in:active,inactive,cancelled,expired',
            'payment_method' => 'nullable|string',
            'payment_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $subscription->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $subscription->load(['user', 'plan']),
            'message' => 'Assinatura atualizada com sucesso'
        ]);
    }

    public function cancel($id)
    {
        $subscription = Subscription::find($id);
        
        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Assinatura cancelada com sucesso'
        ]);
    }

    public function userSubscriptions(Request $request)
    {
        $subscriptions = Subscription::with('plan')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }
}
