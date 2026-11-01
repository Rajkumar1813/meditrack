<?php

declare(strict_types=1);

namespace MediTrack\Enums;

enum PaymentMethod: string
{
    case UPI = 'upi';
    case CARD = 'card';
    case NETBANKING = 'netbanking';
    case COD = 'cod';
}

