<?php

declare(strict_types=1);

namespace MediTrack\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MediTrack\Core\Lang;

class LangTest extends TestCase
{
    public function testTranslationLookupAndPlaceholders(): void
    {
        $langPath = dirname(__DIR__, 3) . '/lang';
        $lang = new Lang($langPath, 'en');

        $enMsg = $lang->get('auth.otp_sent', ['type' => 'email']);
        $this->assertStringContainsString('email', $enMsg);

        $lang->setLocale('hi');
        $hiMsg = $lang->get('auth.otp_sent', ['type' => 'मोबाइल'], 'hi');
        $this->assertStringContainsString('मोबाइल', $hiMsg);
        $this->assertStringContainsString('ओटीपी', $hiMsg);
    }
}
