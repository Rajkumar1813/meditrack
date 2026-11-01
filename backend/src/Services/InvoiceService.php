<?php

declare(strict_types=1);

namespace MediTrack\Services;

use Dompdf\Dompdf;
use MediTrack\Core\Database;
use MediTrack\Core\Exceptions\ForbiddenException;
use MediTrack\Core\Exceptions\NotFoundException;
use MediTrack\Core\Exceptions\ValidationException;
use MediTrack\Policies\OwnershipPolicy;
use MediTrack\Repositories\InvoiceRepository;
use MediTrack\Repositories\NotificationRepository;
use MediTrack\Repositories\OrderRepository;
use MediTrack\Repositories\SettingRepository;
use MediTrack\Repositories\UserRepository;
use Monolog\Logger;

class InvoiceService
{
    private Database $db;
    private InvoiceRepository $invoiceRepo;
    private OrderRepository $orderRepo;
    private UserRepository $userRepo;
    private SettingRepository $settingRepo;
    private ?NotificationRepository $notificationRepo;
    private ?Logger $logger;
    private string $storageDir;
    private string $templatePath;

    public function __construct(
        Database $db,
        InvoiceRepository $invoiceRepo,
        OrderRepository $orderRepo,
        UserRepository $userRepo,
        SettingRepository $settingRepo,
        ?NotificationRepository $notificationRepo = null,
        ?Logger $logger = null,
        ?string $storageDir = null,
        ?string $templatePath = null
    ) {
        $this->db = $db;
        $this->invoiceRepo = $invoiceRepo;
        $this->orderRepo = $orderRepo;
        $this->userRepo = $userRepo;
        $this->settingRepo = $settingRepo;
        $this->notificationRepo = $notificationRepo;
        $this->logger = $logger;
        $this->storageDir = $storageDir ?? (dirname(__DIR__, 2) . '/storage/invoices');
        $this->templatePath = $templatePath ?? (dirname(__DIR__, 2) . '/templates/invoices/pdf.php');
    }

    /**
     * Generate Dompdf GST Invoice for an order.
     * Enforces gap-free sequential numbering INV-YYYY-000001.
     * Returns invoice record.
     */
    public function generateForOrder(int $orderId): array
    {
        // 1. Check if invoice already exists
        $existing = $this->invoiceRepo->findByOrderId($orderId);
        if ($existing && !empty($existing['pdf_path']) && file_exists($existing['pdf_path'])) {
            return $existing;
        }

        // 2. Fetch order data
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Order ID {$orderId} not found.", 'ORDER_NOT_FOUND');
        }

        $items = $this->orderRepo->getItemsWithBatches($orderId);
        $customer = $this->userRepo->findById((int)$order['user_id']) ?? [
            'name' => 'Valued Customer',
            'phone' => 'N/A',
            'email' => null,
        ];

        // Format items with batch information
        foreach ($items as &$item) {
            if (!empty($item['allocated_batches'])) {
                $batchNos = array_column($item['allocated_batches'], 'batch_number');
                $expDates = array_filter(array_column($item['allocated_batches'], 'exp_date'));
                $item['batch_no'] = implode(', ', array_unique($batchNos));
                $item['exp_date'] = !empty($expDates) ? reset($expDates) : null;
            } else {
                $item['batch_no'] = $item['batch_no'] ?? 'N/A';
                $item['exp_date'] = $item['exp_date'] ?? null;
            }
        }

        // Address snapshot
        $address = [];
        if (!empty($order['address_snapshot'])) {
            $address = is_string($order['address_snapshot'])
                ? (json_decode($order['address_snapshot'], true) ?? [])
                : $order['address_snapshot'];
        }

        // Settings (shop name, GST no, drug license, owner name)
        $settings = $this->settingRepo->getAll();

        // Inter-state check (e.g. if shop state != customer state)
        $shopState = strtolower(trim($settings['shop_state'] ?? 'Delhi'));
        $custState = strtolower(trim((string)($address['state'] ?? 'Delhi')));
        $isInterState = ($shopState !== '' && $custState !== '' && $shopState !== $custState);

        // Pharmacist details if Rx verified
        $pharmacist = null;
        if (!empty($order['pharmacist_name']) || !empty($order['pharmacist_reg_no'])) {
            $pharmacist = [
                'name' => $order['pharmacist_name'] ?? 'Dispensing Pharmacist',
                'reg_no' => $order['pharmacist_reg_no'] ?? ($settings['drug_license_no'] ?? ''),
            ];
        }

        // 3. Assign gap-free invoice number or use existing
        $year = (int)date('Y', strtotime($order['created_at'] ?? 'now'));
        if ($existing) {
            $invoiceNo = $existing['invoice_no'];
            $invoiceId = (int)$existing['id'];
        } else {
            $invoiceNo = $this->invoiceRepo->getNextInvoiceNumber($year);
        }

        $invoiceData = [
            'invoice_no' => $invoiceNo,
            'issued_at' => date('Y-m-d H:i:s'),
        ];

        // 4. Render HTML template
        $html = $this->renderInvoiceHtml([
            'invoice' => $invoiceData,
            'order' => $order,
            'items' => $items,
            'settings' => $settings,
            'customer' => $customer,
            'address' => $address,
            'pharmacist' => $pharmacist,
            'isInterState' => $isInterState,
        ]);

        // 5. Generate PDF with Dompdf
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        $filename = "{$invoiceNo}.pdf";
        $pdfPath = $this->storageDir . '/' . $filename;

        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont' => 'Helvetica',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($pdfPath, $dompdf->output());

        // 6. Record or update in database
        if ($existing) {
            $this->invoiceRepo->updatePdfPath($invoiceId, $pdfPath);
        } else {
            $invoiceId = $this->invoiceRepo->create($orderId, $invoiceNo, $pdfPath);
        }

        if ($this->logger) {
            $this->logger->info("Generated GST Invoice {$invoiceNo} for Order #{$order['order_no']}", [
                'order_id' => $orderId,
                'invoice_no' => $invoiceNo,
                'pdf_path' => $pdfPath,
            ]);
        }

        return [
            'id' => $invoiceId,
            'order_id' => $orderId,
            'invoice_no' => $invoiceNo,
            'pdf_path' => $pdfPath,
            'issued_at' => $invoiceData['issued_at'],
        ];
    }

    /**
     * Retrieve the PDF file path for an order, enforcing ownership authorization.
     */
    public function getInvoicePdfPath(int $orderId, ?array $currentUser = null): string
    {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Order ID {$orderId} not found.", 'ORDER_NOT_FOUND');
        }

        // Ownership policy check: customer can only access their own invoice
        if ($currentUser !== null && !OwnershipPolicy::canAccessOrder($currentUser, $order)) {
            throw new ForbiddenException('You do not have permission to access this invoice.', 'FORBIDDEN');
        }

        $invoice = $this->invoiceRepo->findByOrderId($orderId);
        if (!$invoice || empty($invoice['pdf_path']) || !file_exists($invoice['pdf_path'])) {
            // Generate on demand if not generated yet
            $invoice = $this->generateForOrder($orderId);
        }

        if (empty($invoice['pdf_path']) || !file_exists($invoice['pdf_path'])) {
            throw new NotFoundException('Invoice PDF file not found.', 'INVOICE_NOT_FOUND');
        }

        return $invoice['pdf_path'];
    }

    /**
     * Resend invoice link to customer via SMS / notification / email.
     */
    public function resendInvoice(int $invoiceId, string $channel = 'all'): bool
    {
        $invoice = $this->invoiceRepo->findById($invoiceId);
        if (!$invoice) {
            throw new NotFoundException("Invoice ID {$invoiceId} not found.", 'INVOICE_NOT_FOUND');
        }

        $order = $this->orderRepo->findById((int)$invoice['order_id']);
        if (!$order) {
            throw new NotFoundException('Associated order not found.', 'ORDER_NOT_FOUND');
        }

        $user = $this->userRepo->findById((int)$order['user_id']);
        if (!$user) {
            throw new NotFoundException('Customer not found.', 'CUSTOMER_NOT_FOUND');
        }

        $message = "Your invoice {$invoice['invoice_no']} for Order #{$order['order_no']} is ready. You can download it anytime from your MediTrack account.";

        if ($this->notificationRepo) {
            $this->notificationRepo->create(
                (int)$user['id'],
                'invoice',
                'Invoice Ready',
                $message,
                ['order_id' => $order['id'], 'invoice_no' => $invoice['invoice_no']]
            );
        }

        if ($this->logger) {
            $this->logger->info("Resent invoice {$invoice['invoice_no']} to customer #{$user['id']} via {$channel}");
        }

        return true;
    }

    /**
     * Render the PHP invoice template with provided parameters into an HTML string.
     */
    private function renderInvoiceHtml(array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        require $this->templatePath;
        return (string)ob_get_clean();
    }
}
