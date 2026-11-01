<?php

declare(strict_types=1);

namespace MediTrack\Validators;

use MediTrack\Core\Validator;

class PrescriptionReviewValidator
{
    public static function validate(array $data): Validator
    {
        $rules = [
            'status' => 'required|in:approved,rejected',
        ];

        if (($data['status'] ?? '') === 'rejected') {
            $rules['reject_reason'] = 'required|string|min:3|max:255';
        }

        if (($data['status'] ?? '') === 'approved' && !empty($data['valid_until'])) {
            $rules['valid_until'] = 'date';
        }

        return Validator::make($data, $rules, [
            'reject_reason.required' => 'A rejection reason is required when rejecting a prescription.',
        ]);
    }
}

