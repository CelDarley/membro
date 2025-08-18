<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function createPreference(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,semiannual,annual',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $plan = Plan::find($request->plan_id);
        $amount = $plan->getPriceForCycle($request->billing_cycle);
        
        $cycleName = match($request->billing_cycle) {
            'monthly' => 'Mensal',
            'semiannual' => 'Semestral',
            'annual' => 'Anual',
        };

        // Simulação da criação de preferência do Mercado Pago
        // Em produção, aqui seria feita a integração real com a API do Mercado Pago
        $preference = [
            'id' => 'PREF_' . uniqid(),
            'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=PREF_' . uniqid(),
            'items' => [
                [
                    'title' => "Plano {$plan->name} - {$cycleName}",
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'currency_id' => 'BRL',
                ]
            ],
            'back_urls' => [
                'success' => url('/payment/success'),
                'failure' => url('/payment/failure'),
                'pending' => url('/payment/pending'),
            ],
            'auto_return' => 'approved',
            'external_reference' => json_encode([
                'user_id' => $request->user()->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
            ]),
        ];

        return response()->json([
            'success' => true,
            'data' => $preference
        ]);
    }

    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|string',
            'status' => 'required|string',
            'external_reference' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $externalReference = json_decode($request->external_reference, true);
        
        if ($request->status === 'approved') {
            $plan = Plan::find($externalReference['plan_id']);
            $amount = $plan->getPriceForCycle($externalReference['billing_cycle']);
            
            $startDate = Carbon::now();
            $endDate = match($externalReference['billing_cycle']) {
                'monthly' => $startDate->copy()->addMonth(),
                'semiannual' => $startDate->copy()->addMonths(6),
                'annual' => $startDate->copy()->addYear(),
            };

            $subscription = Subscription::create([
                'user_id' => $externalReference['user_id'],
                'plan_id' => $externalReference['plan_id'],
                'billing_cycle' => $externalReference['billing_cycle'],
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_method' => 'mercado_pago',
                'payment_id' => $request->payment_id,
                'payment_data' => $request->all(),
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'data' => $subscription,
                'message' => 'Pagamento processado com sucesso'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Pagamento não aprovado'
        ], 400);
    }

    public function webhook(Request $request)
    {
        // Webhook para receber notificações do Mercado Pago
        // Em produção, aqui seria implementada a lógica para processar
        // as notificações de pagamento do Mercado Pago
        
        \Log::info('Mercado Pago Webhook:', $request->all());

        return response()->json(['status' => 'ok']);
    }

    public function getPaymentMethods()
    {
        // Retorna os métodos de pagamento disponíveis
        $methods = [
            [
                'id' => 'mercado_pago',
                'name' => 'Mercado Pago',
                'description' => 'Cartão de crédito, débito, PIX e boleto',
                'enabled' => true,
            ],
            [
                'id' => 'pix',
                'name' => 'PIX',
                'description' => 'Pagamento instantâneo via PIX',
                'enabled' => true,
            ],
            [
                'id' => 'credit_card',
                'name' => 'Cartão de Crédito',
                'description' => 'Visa, Mastercard, Elo, etc.',
                'enabled' => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $methods
        ]);
    }
}
