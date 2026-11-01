<?php

declare(strict_types=1);

namespace MediTrack\Enums;

enum Role: string
{
    case CUSTOMER = 'customer';
    case PHARMACIST = 'pharmacist';
    case STAFF = 'staff';
    case ADMIN = 'admin';
}

