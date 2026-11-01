<?php

declare(strict_types=1);

namespace MediTrack\Validators;

use MediTrack\Core\Validator;

class SettingValidator
{
    public function validate(array $data): Validator
    {
        $rules = [
            'shop_name' => 'string|min:1|max:120',
            'owner_name' => 'string|min:1|max:120',
            'low_stock_threshold' => 'integer|min:1|max:1000',
            'delivery_fee' => 'numeric|min:0',
        ];

        return Validator::make($data, $rules);
    }
}

