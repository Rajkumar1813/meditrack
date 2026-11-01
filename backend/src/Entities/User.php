<?php

declare(strict_types=1);

namespace MediTrack\Entities;

use MediTrack\Enums\Role;

class User
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $email = null,
        public Role $role = Role::CUSTOMER,
        public string $locale = 'en',
        public bool $isBlocked = false,
        public ?string $pharmacistRegNo = null,
        public ?string $phoneVerifiedAt = null,
        public ?string $emailVerifiedAt = null,
        public ?string $lastLoginAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            name: $data['name'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            role: isset($data['role']) ? Role::from($data['role']) : Role::CUSTOMER,
            locale: $data['locale'] ?? 'en',
            isBlocked: (bool)($data['is_blocked'] ?? false),
            pharmacistRegNo: $data['pharmacist_reg_no'] ?? null,
            phoneVerifiedAt: $data['phone_verified_at'] ?? null,
            emailVerifiedAt: $data['email_verified_at'] ?? null,
            lastLoginAt: $data['last_login_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => $this->role->value,
            'locale' => $this->locale,
            'is_blocked' => $this->isBlocked ? 1 : 0,
            'pharmacist_reg_no' => $this->pharmacistRegNo,
            'phone_verified_at' => $this->phoneVerifiedAt,
            'email_verified_at' => $this->emailVerifiedAt,
            'last_login_at' => $this->lastLoginAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

