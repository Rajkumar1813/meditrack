<?php

declare(strict_types=1);

namespace MediTrack\Enums;

enum Schedule: string
{
    case OTC = 'OTC';
    case H = 'H';
    case H1 = 'H1';
    case X = 'X';
}

