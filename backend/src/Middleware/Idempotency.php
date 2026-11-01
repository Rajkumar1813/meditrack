<?php

declare(strict_types=1);

namespace MediTrack\Middleware;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\OrderRepository;

class Idempotency implements MiddlewareInterface
{
    private OrderRepository $orderRepo;

    public function __construct(OrderRepository $orderRepo)
    {
        $this->orderRepo = $orderRepo;
    }

    public function process(Request $request, callable $next): Response
    {
        $idempotencyKey = $request->header('idempotency-key') ?? $request->header('x-idempotency-key');

        if (!$idempotencyKey || trim($idempotencyKey) === '') {
            throw new ValidationException([
                'idempotency_key' => ['Idempotency-Key header is required.'],
            ]);
        }

        $key = trim($idempotencyKey);
        $userId = (int)($request->attribute('user_id') ?? 0);

        if ($userId > 0) {
            $existing = $this->orderRepo->findByUserAndIdempotencyKey($userId, $key);
            if ($existing) {
                $items = $this->orderRepo->getItems((int)$existing['id']);
                $existing['items'] = $items;
                if (isset($existing['address_snapshot']) && is_string($existing['address_snapshot'])) {
                    $existing['address_snapshot'] = json_decode($existing['address_snapshot'], true);
                }
                return ApiResponse::success($existing, ['idempotent_replay' => true], 200);
            }
        }

        $request = $request->withAttribute('idempotency_key', $key);

        return $next($request);
    }
}

