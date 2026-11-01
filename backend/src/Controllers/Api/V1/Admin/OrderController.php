<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Core\Validator;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Services\OrderService;

class OrderController
{
    private OrderRepository $orderRepo;
    private OrderService $orderService;

    public function __construct(OrderRepository $orderRepo, OrderService $orderService)
    {
        $this->orderRepo = $orderRepo;
        $this->orderService = $orderService;
    }

    public function index(Request $request): Response
    {
        $page = (int)($request->query('page') ?? 1);
        $perPage = (int)($request->query('per_page') ?? 20);
        $sort = (string)($request->query('sort') ?? 'id');
        $dir = (string)($request->query('dir') ?? 'desc');

        $filters = [
            'status' => $request->query('status'),
            'payment_status' => $request->query('payment_status'),
            'payment_method' => $request->query('payment_method'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'q' => $request->query('q') ?? $request->query('search'),
        ];

        $result = $this->orderRepo->paginateAdmin($filters, $page, $perPage, $sort, $dir);

        return ApiResponse::success($result['data'], $result['meta']);
    }

    public function show(Request $request, array $vars): Response
    {
        $orderId = (int)$vars['id'];
        $order = $this->orderRepo->findById($orderId);

        if (!$order) {
            return ApiResponse::notFound('Order not found.');
        }

        $order['items'] = $this->orderRepo->getItemsWithBatches($orderId);
        $order['status_history'] = $this->orderRepo->getStatusHistory($orderId);
        if (isset($order['address_snapshot']) && is_string($order['address_snapshot'])) {
            $order['address_snapshot'] = json_decode($order['address_snapshot'], true);
        }

        return ApiResponse::success($order);
    }

    public function updateStatus(Request $request, array $vars): Response
    {
        $adminId = (int)$request->attribute('user_id');
        $orderId = (int)$vars['id'];
        $body = $request->json();

        $validator = Validator::make($body, [
            'status' => 'required|in:pending,confirmed,packed,shipped,delivered,cancelled',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $toStatus = $body['status'];
        $note = $body['note'] ?? null;
        $extra = [];

        if (!empty($body['courier'])) {
            $extra['courier'] = $body['courier'];
        }
        if (!empty($body['tracking_no'])) {
            $extra['tracking_no'] = $body['tracking_no'];
        }
        if (!empty($body['cancel_reason'])) {
            $extra['cancel_reason'] = $body['cancel_reason'];
            $extra['cancelled_by'] = $adminId;
        }

        $order = $this->orderService->transition($orderId, $toStatus, $adminId, $note, $extra);

        return ApiResponse::success($order, ['message' => "Order updated to {$toStatus}."]);
    }
}

