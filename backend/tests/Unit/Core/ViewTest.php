<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\View;

class ViewTest extends TestCase
{
    public function testTemplateRenderingAndEscaping(): void
    {
        $rendered = View::render('emails/otp', [
            'otp' => '984512',
        ]);

        $this->assertStringContainsString('984512', $rendered);
        $this->assertStringContainsString('Verification Code', $rendered);
    }
}

