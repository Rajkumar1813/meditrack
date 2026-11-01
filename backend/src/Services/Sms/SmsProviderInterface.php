<?php

declare(strict_types=1);

namespace MediTrack\Services\Sms;

interface SmsProviderInterface
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param string $to 10-digit Indian mobile number or E.164 (+91...)
     * @param string $message Text content
     * @return bool True if queued/sent successfully
     */
    public function send(string $to, string $message): bool;
}

