<?php

declare(strict_types=1);

namespace MediTrack\Enums;

enum RxStatus: string
{
    case NOT_REQUIRED = 'not_required';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

