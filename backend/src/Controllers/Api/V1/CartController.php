<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Validator;
use MediTrack\Services\CartService;

class CartController
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $summary = $this->cartService->getSummary($userId);

        return ApiResponse::success($summary);
    }

    public function addItem(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $body = $request->json();

        $validator = Validator::make($body, [
            'medicine_id' => 'required|integer|min:1',
            'quantity' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $medicineId = (int)$body['medicine_id'];
        $quantity = isset($body['quantity']) ? (int)$body['quantity'] : 1;

        $summary = $this->cartService->addItem($userId, $medicineId, $quantity);

        return ApiResponse::success($summary, ['message' => 'Item added to cart.'], 200);
    }

    public function updateItem(Request $request, array $vars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $medicineId = (int)$vars['medicine_id'];
        $body = $request->json();

        $validator = Validator::make($body, [
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $quantity = (int)$body['quantity'];
        $summary = $this->cartService->updateItem($userId, $medicineId, $quantity);

        return ApiResponse::success($summary, ['message' => 'Cart updated.']);
    }

    public function removeItem(Request $request, array $vars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $medicineId = (int)$vars['medicine_id'];

        $summary = $this->cartService->removeItem($userId, $medicineId);

        return ApiResponse::success($summary, ['message' => 'Item removed from cart.']);
    }

    public function clear(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $summary = $this->cartService->clear($userId);

        return ApiResponse::success($summary, ['message' => 'Cart cleared.']);
    }
}

