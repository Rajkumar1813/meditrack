<?php

declare(strict_types=1);

namespace MediTrack\Controllers\Api\V1\Admin;

use MediTrack\Core\ApiResponse;
use MediTrack\Core\Request;
use MediTrack\Core\Response;
use MediTrack\Repositories\InvoiceRepository;
use MediTrack\Services\InvoiceService;

class InvoiceController
{
    private InvoiceRepository $invoiceRepo;
    private InvoiceService $invoiceService;

    public function __construct(InvoiceRepository $invoiceRepo, InvoiceService $invoiceService)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->invoiceService = $invoiceService;
    }

    /**
     * List invoices with search and date range filters (offset pagination).
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->query('search') ?? $request->query('q'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $page = (int)($request->query('page') ?? 1);
        $perPage = (int)($request->query('per_page') ?? 20);

        $result = $this->invoiceRepo->paginateAdmin($filters, $page, $perPage);

        return ApiResponse::success($result['data'], $result['meta']);
    }

    /**
     * Show a single invoice's details.
     */
    public function show(Request $request, string|int|array $idOrVars): Response
    {
        $invoiceId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $invoice = $this->invoiceRepo->findById($invoiceId);

        if (!$invoice) {
            return ApiResponse::notFound('Invoice not found.');
        }

        return ApiResponse::success($invoice);
    }

    /**
     * Download or stream the invoice PDF as admin.
     */
    public function download(Request $request, string|int|array $idOrVars): Response
    {
        $invoiceId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $invoice = $this->invoiceRepo->findById($invoiceId);

        if (!$invoice) {
            return ApiResponse::notFound('Invoice not found.');
        }

        $pdfPath = $this->invoiceService->getInvoicePdfPath((int)$invoice['order_id']);
        $pdfBytes = (string)file_get_contents($pdfPath);
        $filename = basename($pdfPath);

        return new Response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Content-Length' => (string)strlen($pdfBytes),
        ]);
    }

    /**
     * Resend invoice link to customer via email / SMS.
     */
    public function resend(Request $request, string|int|array $idOrVars): Response
    {
        $invoiceId = is_array($idOrVars) ? (int)($idOrVars['id'] ?? 0) : (int)$idOrVars;
        $body = $request->json();
        $channel = $body['channel'] ?? 'all';

        $this->invoiceService->resendInvoice($invoiceId, $channel);

        return ApiResponse::success(['sent' => true], ['message' => 'Invoice resent successfully.']);
    }
}
