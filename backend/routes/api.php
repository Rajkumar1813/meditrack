<?php

declare(strict_types=1);

use MediTrack\Core\Router;
use MediTrack\Enums\Role;
use MediTrack\Controllers\HealthController;
use MediTrack\Controllers\Api\V1\Store\BrandController;
use MediTrack\Controllers\Api\V1\Store\CategoryController;
use MediTrack\Controllers\Api\V1\Store\MedicineController;
use MediTrack\Controllers\Api\V1\Auth\AuthController;
use MediTrack\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use MediTrack\Controllers\Api\V1\Admin\MedicineController as AdminMedicineController;
use MediTrack\Controllers\Api\V1\Admin\BatchController as AdminBatchController;
use MediTrack\Controllers\Api\V1\Admin\ExpiryController as AdminExpiryController;
use MediTrack\Controllers\Api\V1\Admin\RackController as AdminRackController;
use MediTrack\Controllers\Api\V1\Admin\ShelfController as AdminShelfController;
use MediTrack\Controllers\Api\V1\Admin\ImportExportController as AdminImportExportController;
use MediTrack\Controllers\Api\V1\Admin\SettingController as AdminSettingController;
use MediTrack\Controllers\Api\V1\Admin\StockMovementController as AdminStockMovementController;
use MediTrack\Controllers\Api\V1\Admin\AuditLogController as AdminAuditLogController;
use MediTrack\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use MediTrack\Controllers\Api\V1\Admin\PrescriptionController as AdminPrescriptionController;
use MediTrack\Controllers\Api\V1\Admin\RefundController as AdminRefundController;
use MediTrack\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use MediTrack\Controllers\Api\V1\Admin\InvoiceController as AdminInvoiceController;
use MediTrack\Controllers\Api\V1\CartController;
use MediTrack\Controllers\Api\V1\AddressController;
use MediTrack\Controllers\Api\V1\OrderController;
use MediTrack\Controllers\Api\V1\PrescriptionController;
use MediTrack\Controllers\Api\V1\PaymentController;
use MediTrack\Controllers\Api\V1\WebhookController;
use MediTrack\Controllers\Api\V1\DeviceController;
use MediTrack\Controllers\Api\V1\NotificationController;
use MediTrack\Controllers\Api\V1\RefillController;
use MediTrack\Middleware\ForceJson;
use MediTrack\Middleware\Cors;
use MediTrack\Middleware\SecurityHeaders;
use MediTrack\Middleware\OriginCheck;
use MediTrack\Middleware\SetLocale;
use MediTrack\Middleware\RateLimit;
use MediTrack\Middleware\Auth;
use MediTrack\Middleware\RequireRole;
use MediTrack\Middleware\Idempotency;

/** @var Router $router */

$router->get('/health', [HealthController::class, 'check'], [ForceJson::class, Cors::class]);
$router->get('/status', [HealthController::class, 'check'], [ForceJson::class, Cors::class]);

$router->group([
    'prefix' => '/api/v1',
    'middleware' => [
        ForceJson::class,
        Cors::class,
        SecurityHeaders::class,
        OriginCheck::class,
        SetLocale::class,
    ],
], function (Router $r) {
    // Health & Status
    $r->get('/health', [HealthController::class, 'check']);
    $r->get('/status', [HealthController::class, 'check']);

    // Brand
    $r->get('/brand', [BrandController::class, 'show']);

    // Authentication (Phase 2)
    $r->post('/auth/otp/request', [AuthController::class, 'requestOtp'], [new RateLimit('otp_request')]);
    $r->post('/auth/otp/verify', [AuthController::class, 'verifyOtp'], [new RateLimit('otp_verify')]);
    $r->post('/auth/refresh', [AuthController::class, 'refresh']);
    $r->post('/auth/logout', [AuthController::class, 'logout']);
    $r->get('/me', [AuthController::class, 'me'], [Auth::class, new RateLimit('authenticated')]);

    // Storefront Catalog (Phase 3)
    $r->get('/categories', [CategoryController::class, 'index']);
    $r->get('/medicines', [MedicineController::class, 'index'], [new RateLimit('public_catalog')]);
    $r->get('/medicines/{slug}', [MedicineController::class, 'show']);
    $r->get('/search/suggest', [MedicineController::class, 'suggest'], [new RateLimit('search_suggest')]);

    // Cart (Phase 5)
    $r->group([
        'prefix' => '/cart',
        'middleware' => [Auth::class, new RateLimit('authenticated')],
    ], function (Router $cart) {
        $cart->get('', [CartController::class, 'index']);
        $cart->delete('', [CartController::class, 'clear']);
        $cart->post('/items', [CartController::class, 'addItem']);
        $cart->patch('/items/{medicine_id}', [CartController::class, 'updateItem']);
        $cart->delete('/items/{medicine_id}', [CartController::class, 'removeItem']);
    });

    // Addresses (Phase 5)
    $r->group([
        'prefix' => '/addresses',
        'middleware' => [Auth::class, new RateLimit('authenticated')],
    ], function (Router $addr) {
        $addr->get('', [AddressController::class, 'index']);
        $addr->post('', [AddressController::class, 'store']);
        $addr->get('/{id}', [AddressController::class, 'show']);
        $addr->patch('/{id}', [AddressController::class, 'update']);
        $addr->delete('/{id}', [AddressController::class, 'destroy']);
        $addr->post('/{id}/default', [AddressController::class, 'setDefault']);
    });

    // Orders (Phase 5)
    $r->group([
        'prefix' => '/orders',
        'middleware' => [Auth::class],
    ], function (Router $ord) {
        $ord->post('', [OrderController::class, 'store'], [Idempotency::class, new RateLimit('orders_create')]);
        $ord->get('', [OrderController::class, 'index'], [new RateLimit('authenticated')]);
        $ord->get('/{id:\d+}/invoice', [OrderController::class, 'invoice'], [new RateLimit('authenticated')]);
        $ord->get('/{id:\d+}', [OrderController::class, 'show'], [new RateLimit('authenticated')]);
        $ord->get('/{id}', [OrderController::class, 'show'], [new RateLimit('authenticated')]);
        $ord->post('/{id:\d+}/cancel', [OrderController::class, 'cancel'], [new RateLimit('authenticated')]);
        $ord->post('/{id:\d+}/reorder', [OrderController::class, 'reorder'], [new RateLimit('authenticated')]);
    });

    // Prescriptions (Phase 6)
    $r->group([
        'prefix' => '/prescriptions',
        'middleware' => [Auth::class],
    ], function (Router $rx) {
        $rx->post('', [PrescriptionController::class, 'store'], [new RateLimit('prescriptions_upload')]);
        $rx->get('', [PrescriptionController::class, 'index'], [new RateLimit('authenticated')]);
        $rx->get('/reusable', [PrescriptionController::class, 'reusable'], [new RateLimit('authenticated')]);
        $rx->get('/{id:\d+}', [PrescriptionController::class, 'show'], [new RateLimit('authenticated')]);
        $rx->get('/{id:\d+}/files/{file_id:\d+}', [PrescriptionController::class, 'downloadFile']);
    });

    // Payments (Phase 7)
    $r->group([
        'prefix' => '/payments',
        'middleware' => [Auth::class, new RateLimit('payments')],
    ], function (Router $pay) {
        $pay->post('/create', [PaymentController::class, 'create']);
        $pay->post('/verify', [PaymentController::class, 'verify']);
    });

    // Webhooks (Phase 7 - Rate limit & auth exempt; HMAC verified)
    $r->post('/webhooks/razorpay', [WebhookController::class, 'razorpay']);

    // Devices (Phase 9)
    $r->group([
        'prefix' => '/devices',
        'middleware' => [Auth::class, new RateLimit('authenticated')],
    ], function (Router $dev) {
        $dev->post('', [DeviceController::class, 'register']);
        $dev->delete('', [DeviceController::class, 'unregister']);
        $dev->delete('/{token}', [DeviceController::class, 'unregister']);
    });

    // Notifications (Phase 9)
    $r->group([
        'prefix' => '/notifications',
        'middleware' => [Auth::class, new RateLimit('authenticated')],
    ], function (Router $notif) {
        $notif->get('', [NotificationController::class, 'index']);
        $notif->post('/read', [NotificationController::class, 'markRead']);
        $notif->get('/preferences', [NotificationController::class, 'getPreferences']);
        $notif->put('/preferences', [NotificationController::class, 'updatePreferences']);
    });

    // Refill Reminders (Phase 9)
    $r->group([
        'prefix' => '/refills',
        'middleware' => [Auth::class, new RateLimit('authenticated')],
    ], function (Router $ref) {
        $ref->get('', [RefillController::class, 'index']);
        $ref->post('/{id:\d+}/snooze', [RefillController::class, 'snooze']);
        $ref->post('/{id:\d+}/dismiss', [RefillController::class, 'dismiss']);
        $ref->post('/{id:\d+}/reorder', [RefillController::class, 'reorder']);
    });

    // Admin Inventory & Operations (Phase 4)
    $r->group([
        'prefix' => '/admin',
        'middleware' => [
            Auth::class,
            new RequireRole([Role::ADMIN, Role::PHARMACIST]),
            new RateLimit('admin'),
        ],
    ], function (Router $adm) {
        // Dashboard Stats
        $adm->get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

        // CSV Import / Export (Static routes MUST come before variable routes)
        $adm->get('/medicines/export', [AdminImportExportController::class, 'export']);
        $adm->post('/medicines/import/preview', [AdminImportExportController::class, 'preview']);
        $adm->post('/medicines/import/commit', [AdminImportExportController::class, 'commit']);

        // Medicines CRUD
        $adm->get('/medicines', [AdminMedicineController::class, 'index']);
        $adm->post('/medicines', [AdminMedicineController::class, 'store']);
        $adm->get('/medicines/{id}', [AdminMedicineController::class, 'show']);
        $adm->put('/medicines/{id}', [AdminMedicineController::class, 'update']);
        $adm->delete('/medicines/{id}', [AdminMedicineController::class, 'destroy']);

        // Batches CRUD & Disposal
        $adm->get('/medicines/{id}/batches', [AdminBatchController::class, 'index']);
        $adm->post('/medicines/{id}/batches', [AdminBatchController::class, 'store']);
        $adm->put('/batches/{id}', [AdminBatchController::class, 'update']);
        $adm->post('/batches/{id}/dispose', [AdminBatchController::class, 'dispose']);
        $adm->delete('/batches/{id}', [AdminBatchController::class, 'destroy']);

        // Expiry & Stock Auditing
        $adm->get('/expiring', [AdminExpiryController::class, 'expiring']);
        $adm->get('/expired', [AdminExpiryController::class, 'expired']);
        $adm->get('/low-stock', [AdminExpiryController::class, 'lowStock']);

        // Racks & Shelves CRUD
        $adm->get('/racks', [AdminRackController::class, 'index']);
        $adm->post('/racks', [AdminRackController::class, 'store']);
        $adm->get('/racks/{id}', [AdminRackController::class, 'show']);
        $adm->put('/racks/{id}', [AdminRackController::class, 'update']);
        $adm->delete('/racks/{id}', [AdminRackController::class, 'destroy']);
        $adm->get('/racks/{id}/medicines', [AdminRackController::class, 'medicines']);

        $adm->get('/shelves', [AdminShelfController::class, 'index']);
        $adm->post('/shelves', [AdminShelfController::class, 'store']);
        $adm->get('/shelves/{id}', [AdminShelfController::class, 'show']);
        $adm->put('/shelves/{id}', [AdminShelfController::class, 'update']);
        $adm->delete('/shelves/{id}', [AdminShelfController::class, 'destroy']);

        // Settings & Backup
        $adm->get('/settings', [AdminSettingController::class, 'show']);
        $adm->put('/settings', [AdminSettingController::class, 'update']);
        $adm->post('/backup', [AdminSettingController::class, 'createBackup']);
        $adm->get('/backup/download', [AdminSettingController::class, 'downloadBackup']);

        // Stock Movements & Audit Logs
        $adm->get('/stock-movements', [AdminStockMovementController::class, 'index']);
        $adm->get('/audit-logs', [AdminAuditLogController::class, 'index']);

        // Orders Management (Phase 5)
        $adm->get('/orders', [AdminOrderController::class, 'index']);
        $adm->get('/orders/{id}', [AdminOrderController::class, 'show']);
        $adm->patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

        // Prescriptions Verification & Review (Phase 6)
        $adm->get('/prescriptions', [AdminPrescriptionController::class, 'index']);
        $adm->get('/prescriptions/{id:\d+}', [AdminPrescriptionController::class, 'show']);
        $adm->patch('/prescriptions/{id:\d+}/review', [AdminPrescriptionController::class, 'review']);

        // Refunds (Phase 7)
        $adm->post('/orders/{id:\d+}/refund', [AdminRefundController::class, 'store']);

        // Customers Management (Phase 8 - DPDP Act Compliance)
        $adm->get('/customers/delete-requests', [AdminCustomerController::class, 'deleteRequestsList']);
        $adm->get('/customers', [AdminCustomerController::class, 'index']);
        $adm->get('/customers/{id:\d+}', [AdminCustomerController::class, 'show']);
        $adm->post('/customers/{id:\d+}/block', [AdminCustomerController::class, 'block']);
        $adm->post('/customers/{id:\d+}/unblock', [AdminCustomerController::class, 'unblock']);
        $adm->get('/customers/{id:\d+}/export', [AdminCustomerController::class, 'export']);
        $adm->post('/customers/{id:\d+}/delete-request', [AdminCustomerController::class, 'deleteRequest']);

        // Invoices Management (Phase 8)
        $adm->get('/invoices', [AdminInvoiceController::class, 'index']);
        $adm->get('/invoices/{id:\d+}', [AdminInvoiceController::class, 'show']);
        $adm->get('/invoices/{id:\d+}/download', [AdminInvoiceController::class, 'download']);
        $adm->post('/invoices/{id:\d+}/resend', [AdminInvoiceController::class, 'resend']);
    });
});

