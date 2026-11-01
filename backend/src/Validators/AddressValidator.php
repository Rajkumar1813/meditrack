<?php

declare(strict_types=1);

namespace MediTrack\Validators;

use MediTrack\Core\Validator;

class AddressValidator
{
    public static function validate(array $data, bool $isUpdate = false): Validator
    {
        $rules = [
            'recipient_name' => $isUpdate ? 'string|min:2|max:120' : 'required|string|min:2|max:120',
            'phone' => $isUpdate ? 'string|min:10|max:20' : 'required|string|min:10|max:20',
            'line1' => $isUpdate ? 'string|min:3|max:255' : 'required|string|min:3|max:255',
            'city' => $isUpdate ? 'string|min:2|max:80' : 'required|string|min:2|max:80',
            'state' => $isUpdate ? 'string|min:2|max:80' : 'required|string|min:2|max:80',
            'pincode' => $isUpdate ? 'string|pincode' : 'required|string|pincode',
        ];

        return Validator::make($data, $rules, [
            'pincode.regex' => 'Pincode must be a valid 6-digit Indian postal code.',
        ]);
    }
}
