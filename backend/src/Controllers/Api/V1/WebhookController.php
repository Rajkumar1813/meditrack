<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Services\PaymentService;

class WebhookController
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function razorpay(Request $request): Response
    {
        $signature = $request->header('x-razorpay-signature');
        if (!$signature || trim($signature) === '') {
            return ApiResponse::error('MISSING_SIGNATURE', 'X-Razorpay-Signature header is required.', 400);
        }

        $rawBody = $request->rawBody();
        if ($rawBody === '') {
            return ApiResponse::error('EMPTY_BODY', 'Webhook payload is empty.', 400);
        }

        try {
            $result = $this->paymentService->handleRazorpayWebhook($rawBody, $signature);
            return ApiResponse::success($result, 200, ['message' => 'Webhook received.']);
        } catch (ValidationException $e) {
            return ApiResponse::error('INVALID_SIGNATURE', 'Invalid webhook signature.', 400);
        } catch (\Throwable $e) {
            return ApiResponse::error('WEBHOOK_PROCESSING_FAILED', $e->getMessage(), 500);
        }
    }
}

