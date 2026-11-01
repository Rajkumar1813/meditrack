<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Services\CustomerService;

class CustomerController
{
    private CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * List customers with pagination, search, order count, and lifetime value.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->query('search') ?? $request->query('q'),
            'is_blocked' => $request->query('is_blocked'),
            'role' => $request->query('role'),
        ];

        $page = (int)($request->query('page') ?? 1);
        $perPage = (int)($request->query('per_page') ?? 20);

        $result = $this->customerService->listCustomers($filters, $page, $perPage);

        return ApiResponse::success($result['data'], $result['meta']);
    }

    /**
     * Get detailed customer record (profile, statistics, addresses, order history).
     */
    public function show(Request $request, string|int|array $idOrVars): Response
    {
        $userId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $details = $this->customerService->getCustomerDetails($userId);

        return ApiResponse::success($details);
    }

    /**
     * Block customer account and immediately revoke all active refresh tokens.
     */
    public function block(Request $request, string|int|array $idOrVars): Response
    {
        $userId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $adminId = (int)$request->attribute('user_id');
        $body = $request->json();
        $reason = $body['reason'] ?? null;
        $ip = $request->ip();

        $updated = $this->customerService->blockCustomer($userId, $adminId, $reason, $ip);

        return ApiResponse::success($updated, ['message' => 'Customer blocked successfully. All active sessions revoked.']);
    }

    /**
     * Unblock customer account.
     */
    public function unblock(Request $request, string|int|array $idOrVars): Response
    {
        $userId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $adminId = (int)$request->attribute('user_id');
        $ip = $request->ip();

        $updated = $this->customerService->unblockCustomer($userId, $adminId, $ip);

        return ApiResponse::success($updated, ['message' => 'Customer unblocked successfully.']);
    }

    /**
     * Export full customer personal data under DPDP Act 2023.
     */
    public function export(Request $request, string|int|array $idOrVars): Response
    {
        $userId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $exportData = $this->customerService->exportCustomerData($userId);

        return ApiResponse::success($exportData, ['message' => 'Customer personal data exported in compliance with DPDP Act 2023.']);
    }

    /**
     * Submit an account deletion request under DPDP Act 2023.
     */
    public function deleteRequest(Request $request, string|int|array $idOrVars): Response
    {
        $userId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $body = $request->json();
        $reason = $body['reason'] ?? null;

        $result = $this->customerService->recordDeleteRequest($userId, $reason);

        return ApiResponse::success($result, ['message' => 'Account deletion request recorded successfully under DPDP Act.']);
    }

    /**
     * List all pending and historical account deletion requests.
     */
    public function deleteRequestsList(Request $request): Response
    {
        $page = (int)($request->query('page') ?? 1);
        $perPage = (int)($request->query('per_page') ?? 20);

        $result = $this->customerService->getDeleteRequests($page, $perPage);

        return ApiResponse::success($result['data'], $result['meta']);
    }
}
