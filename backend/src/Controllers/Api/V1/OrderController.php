<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Validator;
use MediTrack\Policies\OwnershipPolicy;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Services\InvoiceService;
use MediTrack\Services\OrderService;

class OrderController
{
    private OrderService $orderService;
    private OrderRepository $orderRepo;
    private ?InvoiceService $invoiceService;

    public function __construct(
        OrderService $orderService,
        OrderRepository $orderRepo,
        ?InvoiceService $invoiceService = null
    ) {
        $this->orderService = $orderService;
        $this->orderRepo = $orderRepo;
        $this->invoiceService = $invoiceService;
    }

    public function store(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $idempotencyKey = $request->attribute('idempotency_key');
        $body = $request->json();

        $validator = Validator::make($body, [
            'address_id' => 'required|integer|min:1',
            'payment_method' => 'required|in:cod,upi,card,netbanking',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $order = $this->orderService->createOrder($userId, $body, $idempotencyKey);

        return ApiResponse::success($order, ['message' => 'Order created successfully.'], 201);
    }

    public function index(Request $request): Response
    {
        $userId = (int)$request->attribute('user_id');
        $cursor = $request->query('cursor');
        $limit = (int)($request->query('limit') ?? 20);

        $result = $this->orderRepo->findByUser($userId, $cursor, $limit);

        return ApiResponse::success($result['data'], $result['meta']);
    }

    public function show(Request $request, string|int|array $idOrVars): Response
    {
        $user = $request->attribute('user');
        $orderId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;

        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            return ApiResponse::notFound('Order not found.');
        }

        // Ownership Policy IDOR check: User B cannot view User A's order
        if (!OwnershipPolicy::canAccessOrder($user, $order)) {
            return ApiResponse::forbidden('You do not have permission to view this order.');
        }

        $order['items'] = $this->orderRepo->getItemsWithBatches($orderId);
        $order['status_history'] = $this->orderRepo->getStatusHistory($orderId);
        if (isset($order['address_snapshot']) && is_string($order['address_snapshot'])) {
            $order['address_snapshot'] = json_decode($order['address_snapshot'], true);
        }

        return ApiResponse::success($order);
    }

    public function cancel(Request $request, string|int|array $idOrVars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $orderId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $body = $request->json();

        $reason = $body['reason'] ?? 'Cancelled by customer';
        $order = $this->orderService->cancelByCustomer($orderId, $userId, $reason);

        return ApiResponse::success($order, ['message' => 'Order cancelled successfully.']);
    }

    public function reorder(Request $request, string|int|array $idOrVars): Response
    {
        $userId = (int)$request->attribute('user_id');
        $orderId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;

        $summary = $this->orderService->reorder($orderId, $userId);

        return ApiResponse::success($summary, ['message' => 'Items from order added to cart.']);
    }

    public function invoice(Request $request, string|int|array $idOrVars): Response
    {
        $user = $request->attribute('user');
        $orderId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;

        if (!$this->invoiceService) {
            return ApiResponse::error('SERVICE_UNAVAILABLE', 'Invoice service not available.', 500);
        }

        try {
            $pdfPath = $this->invoiceService->getInvoicePdfPath($orderId, $user);
        } catch (\MediTrack\Core\Exceptions\ForbiddenException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (\MediTrack\Core\Exceptions\NotFoundException $e) {
            return ApiResponse::notFound($e->getMessage());
        }

        $pdfBytes = (string)file_get_contents($pdfPath);
        $filename = basename($pdfPath);

        return new Response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Content-Length' => (string)strlen($pdfBytes),
        ]);
    }
}

