<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Validator;
use MediTrack\Core\Exceptions\ValidationException;

class ValidatorTest extends TestCase
{
    public function testValidationPasses(): void
    {
        $data = [
            'name' => 'Paracetamol 500',
            'qty' => 100,
            'email' => 'pharmacist@example.com',
            'phone' => '+919876543210',
            'mfg_date' => '2025-01-01',
            'exp_date' => '2026-01-01',
            'purchase_price' => 10,
            'mrp' => 15,
        ];

        $rules = [
            'name' => 'required|string|min:3',
            'qty' => 'required|integer|min:0',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'mfg_date' => 'required|date',
            'exp_date' => 'required|date|after_date:mfg_date',
            'purchase_price' => 'required|numeric',
            'mrp' => 'required|numeric|gte_field:purchase_price',
        ];

        $v = Validator::make($data, $rules);
        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
        $this->assertSame($data, $v->validated());
    }

    public function testValidationFailsAndThrowsValidationException(): void
    {
        $data = [
            'name' => '',
            'qty' => -5,
            'email' => 'not-an-email',
            'phone' => '123',
            'mfg_date' => '2026-01-01',
            'exp_date' => '2025-01-01', // Before mfg_date!
            'purchase_price' => 20,
            'mrp' => 10, // mrp < purchase_price!
        ];

        $rules = [
            'name' => 'required',
            'qty' => 'required|min:0',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'exp_date' => 'required|after_date:mfg_date',
            'mrp' => 'required|gte_field:purchase_price',
        ];

        $v = Validator::make($data, $rules);
        $this->assertTrue($v->fails());
        $errors = $v->errors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('qty', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone', $errors);
        $this->assertArrayHasKey('exp_date', $errors);
        $this->assertArrayHasKey('mrp', $errors);

        $this->expectException(ValidationException::class);
        $v->validated();
    }
}
