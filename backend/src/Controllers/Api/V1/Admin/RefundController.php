<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Validator;
use MediTrack\Services\PaymentService;

class RefundController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function store(Request $request, array $vars): Response
    {
        $adminId = (int)$request->attribute('user_id');
        $orderId = (int)$vars['id'];
        $body = $request->json();

        $validator = Validator::make($body, [
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $amount = (float)$body['amount'];
        $reason = $body['reason'] ?? 'Admin refund';

        $result = $this->paymentService->processRefund($orderId, $amount, $reason, $adminId);

        return ApiResponse::success($result, 200, ['message' => 'Refund processed successfully.']);
    }
}

