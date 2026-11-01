<?php

declare(strict_types=1);

namespace MediTrack\Services;

use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Repositories\AuditLogRepository;
use MediTrack\Repositories\UserRepository;
use Monolog\Logger;

class CustomerService
{
    private UserRepository $userRepo;
    private ?AuditLogRepository $auditLogRepo;
    private ?Logger $logger;

    public function __construct(
        UserRepository $userRepo,
        ?AuditLogRepository $auditLogRepo = null,
        ?Logger $logger = null
    ) {
        $this->userRepo = $userRepo;
        $this->auditLogRepo = $auditLogRepo;
        $this->logger = $logger;
    }

    /**
     * List customers with pagination, lifetime value, and order counts.
     */
    public function listCustomers(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->userRepo->paginateCustomers($filters, $page, $perPage);
    }

    /**
     * Get detailed customer record including profile, statistics, addresses, and order history.
     */
    public function getCustomerDetails(int $userId): array
    {
        $details = $this->userRepo->getCustomerDetails($userId);
        if (!$details) {
            throw new NotFoundException("Customer with ID {$userId} not found.", 'CUSTOMER_NOT_FOUND');
        }
        return $details;
    }

    /**
     * Block a customer account, immediately revoke all active refresh tokens, and log the action.
     */
    public function blockCustomer(int $userId, ?int $adminId = null, ?string $reason = null, ?string $ip = null): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new NotFoundException("Customer with ID {$userId} not found.", 'CUSTOMER_NOT_FOUND');
        }

        $this->userRepo->setBlocked($userId, true);

        if ($this->auditLogRepo) {
            $this->auditLogRepo->log(
                'customer_blocked',
                'user',
                $userId,
                ['is_blocked' => (int)$user['is_blocked']],
                ['is_blocked' => 1, 'reason' => $reason],
                $adminId,
                $ip
            );
        }

        if ($this->logger) {
            $this->logger->warning("Customer #{$userId} was blocked by Admin #{$adminId}", [
                'user_id' => $userId,
                'admin_id' => $adminId,
                'reason' => $reason,
            ]);
        }

        return $this->getCustomerDetails($userId);
    }

    /**
     * Unblock a customer account and log the action.
     */
    public function unblockCustomer(int $userId, ?int $adminId = null, ?string $ip = null): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new NotFoundException("Customer with ID {$userId} not found.", 'CUSTOMER_NOT_FOUND');
        }

        $this->userRepo->setBlocked($userId, false);

        if ($this->auditLogRepo) {
            $this->auditLogRepo->log(
                'customer_unblocked',
                'user',
                $userId,
                ['is_blocked' => (int)$user['is_blocked']],
                ['is_blocked' => 0],
                $adminId,
                $ip
            );
        }

        if ($this->logger) {
            $this->logger->info("Customer #{$userId} was unblocked by Admin #{$adminId}", [
                'user_id' => $userId,
                'admin_id' => $adminId,
            ]);
        }

        return $this->getCustomerDetails($userId);
    }

    /**
     * Export all customer personal data under DPDP Act 2023.
     */
    public function exportCustomerData(int $userId): array
    {
        $data = $this->userRepo->exportCustomerData($userId);
        if (!$data) {
            throw new NotFoundException("Customer with ID {$userId} not found.", 'CUSTOMER_NOT_FOUND');
        }
        return $data;
    }

    /**
     * Submit an account deletion request under DPDP Act 2023.
     */
    public function recordDeleteRequest(int $userId, ?string $reason = null): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new NotFoundException("Customer with ID {$userId} not found.", 'CUSTOMER_NOT_FOUND');
        }

        $requestId = $this->userRepo->createDeleteRequest($userId, $reason);

        if ($this->logger) {
            $this->logger->info("DPDP account deletion request submitted for User #{$userId}", [
                'request_id' => $requestId,
                'user_id' => $userId,
                'reason' => $reason,
            ]);
        }

        return [
            'request_id' => $requestId,
            'user_id' => $userId,
            'status' => 'pending',
            'message' => 'Account deletion request has been recorded in compliance with DPDP Act 2023.',
        ];
    }

    /**
     * Get account deletion requests for admin view.
     */
    public function getDeleteRequests(int $page = 1, int $perPage = 20): array
    {
        return $this->userRepo->getDeleteRequests($page, $perPage);
    }
}
