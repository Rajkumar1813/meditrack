<?php

declare(strict_types=1);

namespace MediTrack\Entities;

use MediTrack\Enums\Schedule;

class Medicine
{
    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public string $slug = '',
        public ?string $genericName = null,
        public ?int $manufacturerId = null,
        public int $categoryId = 1,
        public Schedule $schedule = Schedule::OTC,
        public bool $requiresRx = false,
        public ?string $packSize = null,
        public float $gstPercent = 12.00,
        public ?string $description = null,
        public ?string $usageInfo = null,
        public ?string $sideEffects = null,
        public ?string $storageInfo = null,
        public ?string $image = null,
        public bool $isActive = true,
        public bool $isFeatured = false,
        public ?string $notes = null,
        public ?int $createdBy = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {
        // Enforce: requires_rx = schedule != OTC
        $this->requiresRx = $this->schedule !== Schedule::OTC;
    }

    public static function fromArray(array $data): self
    {
        $sched = isset($data['schedule']) ? Schedule::from($data['schedule']) : Schedule::OTC;
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
            genericName: $data['generic_name'] ?? null,
            manufacturerId: isset($data['manufacturer_id']) ? (int)$data['manufacturer_id'] : null,
            categoryId: (int)($data['category_id'] ?? 1),
            schedule: $sched,
            requiresRx: $sched !== Schedule::OTC,
            packSize: $data['pack_size'] ?? null,
            gstPercent: (float)($data['gst_percent'] ?? 12.00),
            description: $data['description'] ?? null,
            usageInfo: $data['usage_info'] ?? null,
            sideEffects: $data['side_effects'] ?? null,
            storageInfo: $data['storage_info'] ?? null,
            image: $data['image'] ?? null,
            isActive: (bool)($data['is_active'] ?? true),
            isFeatured: (bool)($data['is_featured'] ?? false),
            notes: $data['notes'] ?? null,
            createdBy: isset($data['created_by']) ? (int)$data['created_by'] : null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }
}

