<?php

declare(strict_types=1);

namespace MediTrack\Commands;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    public function shouldRun(\DateTimeImmutable $now): bool;

    public function execute(): int|string;
}

