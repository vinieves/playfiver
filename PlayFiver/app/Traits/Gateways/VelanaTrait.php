<?php

namespace App\Traits\Gateways;

use App\Models\Deposit;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

trait VelanaTrait
{
    /**
     * Generate a PIX charge via Velana.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generatePix(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = auth()->user();
        $secretKey = Setting::where('name', 'velana_secret_key')->first()?->value;

        if (empty($secretKey)) {
            return response()->json(['error' => 'Credenciais da Velana não configuradas.'], 500);
        }

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'gateway' => 'Velana',
            'status' => 'pending',
        ]);

        $payload = [
            'paymentMethod' => 'pix',
            'amount' => $request->amount * 100, // Velana expects amount in cents
            'postbackUrl' => route('velana.webhook'),
            'externalRef' => $deposit->id,
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
                'document' => [
                    'number' => $user->cpf, // Assumes the user model has a 'cpf' attribute
                    'type' => 'cpf',
                ],
            ],
            'items' => [
                [
                    'title' => 'Créditos na Plataforma',
                    'unitPrice' => $request->amount * 100,
                    'quantity' => 1,
                    'tangible' => false,
                ],
            ],
        ];

        $response = Http::withBasicAuth($secretKey, '')
            ->post('https://api.velana.com.br/v1/transactions', $payload);

        if ($response->failed()) {
            $deposit->update(['status' => 'failed']);
            return response()->json(['error' => 'Falha ao comunicar com o gateway de pagamento.'], 500);
        }

        $data = $response->json();

        if (isset($data['pix']['qrCode'])) {
            $deposit->update(['transaction_id' => $data['id']]);

            return response()->json([
                'qr_code' => $data['pix']['qrCode'],
                'pix_url' => $data['pix']['qrCodeText'],
            ]);
        }

        $deposit->update(['status' => 'failed']);
        return response()->json(['error' => 'Resposta inesperada do gateway de pagamento.'], 500);
    }

    /**
     * Handle Velana webhook.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->input('data');

        if (empty($payload)) {
            return response('Payload vazio.', 400);
        }

        if (isset($payload['status']) && $payload['status'] === 'paid') {
            $depositId = $payload['externalRef'] ?? null;
            if (empty($depositId)) {
                return response('externalRef não encontrado.', 400);
            }

            $deposit = Deposit::where('id', $depositId)->where('status', 'pending')->first();

            if ($deposit) {
                $paidAmount = $payload['amount'] / 100;
                if ($paidAmount < $deposit->amount) {
                    return response('Valor pago é menor que o valor do depósito.', 400);
                }

                $deposit->update(['status' => 'completed']);

                $user = User::find($deposit->user_id);
                if ($user && isset($user->wallet)) {
                    $user->wallet->deposit($deposit->amount, ['description' => 'Depósito via Velana PIX. ID: ' . $deposit->id]);
                }
            }
        }

        return response('Webhook recebido.', 200);
    }
} 