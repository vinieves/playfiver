<?php

namespace App\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use App\Traits\Gateways\VelanaTrait;
use Illuminate\Http\Request;

class VelanaController extends Controller
{
    use VelanaTrait;

    /**
     * Generate a PIX charge.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deposit(Request $request)
    {
        return $this->generatePix($request);
    }

    /**
     * Handle webhook notifications.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function webhook(Request $request)
    {
        return $this->handleWebhook($request);
    }
} 