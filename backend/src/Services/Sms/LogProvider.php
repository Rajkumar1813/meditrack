<?php

declare(strict_types=1);

namespace MediTrack\Services\Sms;

use Monolog\Logger;

class LogProvider implements SmsProviderInterface
{
    private ?Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    public function send(string $to, string $message): bool
    {
        $msg = "[SMS LOG] To: {$to} | Message: {$message}";
        if ($this->logger !== null) {
            $this->logger->info($msg);
        } else {
            error_log($msg);
        }
        return true;
    }
}

