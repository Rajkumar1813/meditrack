<?php

declare(strict_types=1);

namespace MediTrack\Core;

use MediTrack\Core\Exceptions\ValidationException;

class Validator
{
    private array $data;
    private array $rules;
    private array $customMessages;
    private array $errors = [];

    public function __construct(array $data, array $rules, array $customMessages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
        $this->validate();
    }

    public static function make(array $data, array $rules, array $customMessages = []): self
    {
        return new self($data, $rules, $customMessages);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function addCustomError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        $validatedData = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validatedData[$field] = $this->data[$field];
            }
        }
        return $validatedData;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleset) {
            $rules = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
            $value = $this->data[$field] ?? null;

            $isRequired = in_array('required', $rules, true);

            if ($isRequired && ($value === null || $value === '' || (is_array($value) && empty($value)))) {
                $this->addError($field, 'required', "The {$field} field is required.");
                continue;
            }

            if ($value === null || $value === '') {
                // Skip further validations if field is optional and empty
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                $ruleName = $rule;
                $parameter = null;

                if (str_contains($rule, ':')) {
                    [$ruleName, $parameter] = explode(':', $rule, 2);
                }

                $this->applyRule($field, $value, $ruleName, $parameter, $rules);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param, array $fieldRules = []): void
    {
        switch ($rule) {
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, 'string', "The {$field} must be a string.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, 'numeric', "The {$field} must be a number.");
                }
                break;

            case 'integer':
                if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
                    $this->addError($field, 'integer', "The {$field} must be an integer.");
                }
                break;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false', true, false], true)) {
                    $this->addError($field, 'boolean', "The {$field} must be a boolean.");
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, 'array', "The {$field} must be an array.");
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'email', "The {$field} must be a valid email address.");
                }
                break;

            case 'phone':
                // Indian phone number regex: +91 or 10 digits
                $clean = preg_replace('/[\s\-\+]/', '', (string)$value);
                if (!preg_match('/^(91)?[6-9]\d{9}$/', $clean)) {
                    $this->addError($field, 'phone', "The {$field} must be a valid 10-digit mobile number.");
                }
                break;

            case 'min':
                $min = (float)$param;
                $isStringRule = in_array('string', $fieldRules, true);
                $isNumericRule = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

                if (is_array($value)) {
                    if (count($value) < (int)$param) {
                        $this->addError($field, 'min', "The {$field} must have at least {$param} items.");
                    }
                } elseif ($isStringRule || (is_string($value) && !$isNumericRule)) {
                    if (mb_strlen((string)$value) < (int)$param) {
                        $this->addError($field, 'min', "The {$field} must be at least {$param} characters.");
                    }
                } elseif (is_numeric($value)) {
                    if ((float)$value < $min) {
                        $this->addError($field, 'min', "The {$field} must be at least {$param}.");
                    }
                }
                break;

            case 'max':
                $max = (float)$param;
                $isStringRule = in_array('string', $fieldRules, true);
                $isNumericRule = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

                if (is_array($value)) {
                    if (count($value) > (int)$param) {
                        $this->addError($field, 'max', "The {$field} may not have more than {$param} items.");
                    }
                } elseif ($isStringRule || (is_string($value) && !$isNumericRule)) {
                    if (mb_strlen((string)$value) > (int)$param) {
                        $this->addError($field, 'max', "The {$field} may not be greater than {$param} characters.");
                    }
                } elseif (is_numeric($value)) {
                    if ((float)$value > $max) {
                        $this->addError($field, 'max', "The {$field} may not be greater than {$param}.");
                    }
                }
                break;

            case 'in':
                $allowed = explode(',', (string)$param);
                if (!in_array((string)$value, $allowed, true)) {
                    $this->addError($field, 'in', "The {$field} must be one of: {$param}.");
                }
                break;

            case 'date':
                if (strtotime((string)$value) === false) {
                    $this->addError($field, 'date', "The {$field} must be a valid date.");
                }
                break;

            case 'after_date':
                $otherField = $param;
                $otherValue = $this->data[$otherField] ?? null;
                if ($otherValue && strtotime((string)$value) <= strtotime((string)$otherValue)) {
                    $this->addError($field, 'after_date', "The {$field} must be after {$otherField}.");
                }
                break;

            case 'gte_field':
                $otherField = $param;
                $otherValue = $this->data[$otherField] ?? null;
                if ($otherValue !== null && (float)$value < (float)$otherValue) {
                    $this->addError($field, 'gte_field', "The {$field} must be greater than or equal to {$otherField}.");
                }
                break;

            case 'lte_field':
                $otherField = $param;
                $otherValue = $this->data[$otherField] ?? null;
                if ($otherValue !== null && (float)$value > (float)$otherValue) {
                    $this->addError($field, 'lte_field', "The {$field} must be less than or equal to {$otherField}.");
                }
                break;

            case 'regex':
                if (!preg_match((string)$param, (string)$value)) {
                    $this->addError($field, 'regex', "The {$field} format is invalid.");
                }
                break;

            case 'pincode':
                if (!preg_match('/^[1-9][0-9]{5}$/', (string)$value)) {
                    $this->addError($field, 'pincode', "The {$field} must be a valid 6-digit Indian postal code.");
                }
                break;
        }
    }

    private function addError(string $field, string $rule, string $defaultMessage): void
    {
        $customKey = "{$field}.{$rule}";
        $message = $this->customMessages[$customKey] ?? $this->customMessages[$field] ?? $defaultMessage;

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}

