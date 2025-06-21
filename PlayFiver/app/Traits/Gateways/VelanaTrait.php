<?php

namespace App\Traits\Gateways;

use App\Models\Deposit;
use App\Models\Gateway;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        Log::info('--- Iniciando geração de PIX via Velana ---');

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = auth()->user();
        $gatewaySettings = \App\Models\Gateway::first();
        $secretKey = $gatewaySettings->velana_secret_key ?? null;
        Log::info('Velana Secret Key encontrada: ' . ($secretKey ? 'Sim' : 'Não'));

        if (empty($secretKey)) {
            Log::error('A Secret Key da Velana não foi configurada.');
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

        Log::info('Payload enviado para Velana:', $payload);

        $response = Http::withBasicAuth($secretKey, '')
            ->post('https://api.velana.com.br/v1/transactions', $payload);

        Log::info('Resposta da API Velana - Status: ' . $response->status());
        Log::info('Resposta da API Velana - Corpo: ', $response->json() ?? ['raw_body' => $response->body()]);

        if ($response->failed()) {
            Log::error('Falha na comunicação com a Velana.', ['status' => $response->status(), 'body' => $response->body()]);
            $deposit->update(['status' => 'failed']);
            return response()->json(['error' => 'Falha ao comunicar com o gateway de pagamento.'], 500);
        }

        $data = $response->json();

        if (isset($data['pix']['qrCodeText'])) {
            $deposit->update(['transaction_id' => $data['id']]);
            Log::info('Sucesso! QR Code da Velana recebido e enviado para o frontend.');

            return response()->json([
                'idTransaction' => $data['id'],
                'qrcode'        => $data['pix']['qrCodeText'],
            ]);
        }

        Log::error('A resposta da Velana não continha o qrCodeText esperado.', ['response_data' => $data]);
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
        Log::info('--- Webhook Velana Recebido ---', $request->all());
        $payload = $request->input('data');

        if (empty($payload)) {
            Log::warning('Webhook Velana com payload vazio.');
            return response('Payload vazio.', 400);
        }

        if (isset($payload['status']) && $payload['status'] === 'paid') {
            $depositId = $payload['externalRef'] ?? null;
            if (empty($depositId)) {
                Log::warning('Webhook Velana sem externalRef.', ['payload' => $payload]);
                return response('externalRef não encontrado.', 400);
            }

            $deposit = Deposit::where('id', $depositId)->where('status', 'pending')->first();

            if ($deposit) {
                $paidAmount = $payload['amount'] / 100;
                if ($paidAmount < $deposit->amount) {
                    Log::warning('Valor pago no webhook da Velana é menor que o valor do depósito.', ['payload' => $payload, 'deposit' => $deposit]);
                    return response('Valor pago é menor que o valor do depósito.', 400);
                }

                $deposit->update(['status' => 'completed']);
                Log::info('Depósito via Velana atualizado para completo.', ['deposit_id' => $deposit->id]);

                $user = User::find($deposit->user_id);
                if ($user && isset($user->wallet)) {
                    $user->wallet->deposit($deposit->amount, ['description' => 'Depósito via Velana PIX. ID: ' . $deposit->id]);
                    Log::info('Saldo creditado na carteira do usuário.', ['user_id' => $user->id, 'amount' => $deposit->amount]);
                }
            } else {
                 Log::info('Depósito já processado ou não encontrado via Webhook Velana.', ['externalRef' => $depositId]);
            }
        }

        return response('Webhook recebido.', 200);
    }
} 