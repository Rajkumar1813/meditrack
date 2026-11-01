<?php

declare(strict_types=1);

namespace MediTrack\Entities;

class Batch
{
    public function __construct(
        public ?int $id = null,
        public int $medicineId = 0,
        public string $batchNumber = '',
        public int $quantity = 0,
        public int $reservedQuantity = 0,
        public float $purchasePrice = 0.0,
        public float $mrp = 0.0,
        public float $sellingPrice = 0.0,
        public string $mfgDate = '',
        public string $expDate = '',
        public ?int $supplierId = null,
        public ?int $rackId = null,
        public ?int $shelfId = null,
        public string $disposalStatus = 'none',
        public ?string $notes = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            medicineId: (int)($data['medicine_id'] ?? 0),
            batchNumber: (string)($data['batch_number'] ?? ''),
            quantity: (int)($data['quantity'] ?? 0),
            reservedQuantity: (int)($data['reserved_quantity'] ?? 0),
            purchasePrice: (float)($data['purchase_price'] ?? 0.0),
            mrp: (float)($data['mrp'] ?? 0.0),
            sellingPrice: (float)($data['selling_price'] ?? 0.0),
            mfgDate: (string)($data['mfg_date'] ?? ''),
            expDate: (string)($data['exp_date'] ?? ''),
            supplierId: isset($data['supplier_id']) ? (int)$data['supplier_id'] : null,
            rackId: isset($data['rack_id']) ? (int)$data['rack_id'] : null,
            shelfId: isset($data['shelf_id']) ? (int)$data['shelf_id'] : null,
            disposalStatus: (string)($data['disposal_status'] ?? 'none'),
            notes: $data['notes'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}

