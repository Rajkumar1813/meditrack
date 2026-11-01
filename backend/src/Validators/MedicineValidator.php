<?php

declare(strict_types=1);

namespace MediTrack\Validators;

use MediTrack\Core\Validator;
use MediTrack\Repositories\MedicineRepository;

class MedicineValidator
{
    private MedicineRepository $medicineRepo;

    public function __construct(MedicineRepository $medicineRepo)
    {
        $this->medicineRepo = $medicineRepo;
    }

    public function validate(array $data, ?int $ignoreMedicineId = null): Validator
    {
        $rules = [
            'name' => 'required|string|min:2|max:150',
            'category_id' => 'required|integer|min:1',
            'schedule' => 'in:OTC,H,H1,X',
        ];

        $validator = Validator::make($data, $rules);

        // Check duplicate slug if slug is provided or generated from name
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        if ($slug === '' && $name !== '') {
            $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        if ($slug !== '') {
            $existing = $this->medicineRepo->findBySlug($slug);
            if ($existing && ($ignoreMedicineId === null || (int)$existing['id'] !== $ignoreMedicineId)) {
                $validator->addCustomError('slug', 'A medicine with this name or slug already exists.');
            }
        }

        return $validator;
    }
}

