<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Validator;
use MediTrack\Services\PaymentService;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function create(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $body = $request->json();

        $validator = Validator::make($body, [
            'order_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $orderId = (int)$body['order_id'];
        $gatewayName = isset($body['gateway']) ? (string)$body['gateway'] : null;

        $payload = $this->paymentService->createPayment($orderId, $userId, $gatewayName);

        return ApiResponse::success($payload, 201, ['message' => 'Payment initiated successfully.']);
    }

    public function verify(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $body = $request->json();

        $validator = Validator::make($body, [
            'order_id' => 'required|integer|min:1',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $orderId = (int)$body['order_id'];
        $result = $this->paymentService->verifyPayment($orderId, $userId, $body);

        return ApiResponse::success($result, 200, ['message' => 'Payment verified successfully.']);
    }
}

