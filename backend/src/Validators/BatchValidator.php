<?php

declare(strict_types=1);

namespace MediTrack\Validators;

use MediTrack\Core\Validator;
use MediTrack\Repositories\BatchRepository;

class BatchValidator
{
    private BatchRepository $batchRepo;

    public function __construct(BatchRepository $batchRepo)
    {
        $this->batchRepo = $batchRepo;
    }

    public function validate(array $data, ?int $ignoreBatchId = null): Validator
    {
        $rules = [
            'medicine_id' => 'required|integer|min:1',
            'batch_number' => 'required|string|min:1|max:60',
            'quantity' => 'required|integer|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'mrp' => 'required|numeric|min:0|gte_field:purchase_price',
            'mfg_date' => 'required|date',
            'exp_date' => 'required|date|after_date:mfg_date',
        ];

        if (isset($data['selling_price'])) {
            $rules['selling_price'] = 'numeric|min:0|lte_field:mrp';
        }

        $expMsg = t('inventory.exp_after_mfg');
        if ($expMsg === 'inventory.exp_after_mfg') {
            $expMsg = 'Expiry date must be after manufacturing date.';
        }
        $mrpMsg = t('inventory.mrp_gte_purchase');
        if ($mrpMsg === 'inventory.mrp_gte_purchase') {
            $mrpMsg = 'MRP must be greater than or equal to purchase price.';
        }
        $sellMsg = t('inventory.selling_price_lte_mrp');
        if ($sellMsg === 'inventory.selling_price_lte_mrp') {
            $sellMsg = 'Selling price must be less than or equal to MRP.';
        }
        $qtyMsg = t('inventory.qty_min_zero');
        if ($qtyMsg === 'inventory.qty_min_zero') {
            $qtyMsg = 'Quantity cannot be negative.';
        }

        $validator = Validator::make($data, $rules, [
            'exp_date.after_date' => $expMsg,
            'mrp.gte_field' => $mrpMsg,
            'selling_price.lte_field' => $sellMsg,
            'quantity.min' => $qtyMsg,
        ]);

        // Duplicate batch check (UNIQUE(medicine_id, batch_number))
        if (!empty($data['medicine_id']) && !empty($data['batch_number'])) {
            $existing = $this->batchRepo->findByMedicineAndBatchNumber(
                (int)$data['medicine_id'],
                (string)$data['batch_number']
            );

            if ($existing && ($ignoreBatchId === null || (int)$existing['id'] !== $ignoreBatchId)) {
                $dupMsg = t('inventory.duplicate_batch');
                if ($dupMsg === 'inventory.duplicate_batch') {
                    $dupMsg = 'Batch number already exists for this medicine.';
                }
                $validator->addCustomError('batch_number', $dupMsg);
            }
        }

        return $validator;
    }
}
