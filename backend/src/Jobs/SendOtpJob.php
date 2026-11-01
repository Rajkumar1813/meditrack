<?php

declare(strict_types=1);

namespace MediTrack\Jobs;

use MediTrack\Core\Container;
use MediTrack\Services\Sms\SmsProviderInterface;
use MediTrack\Services\Email\EmailService;

class SendOtpJob
{
    private string $type;
    private string $identifier;
    private string $otp;

    public function __construct(string $type, string $identifier, string $otp)
    {
        $this->type = $type;
        $this->identifier = $identifier;
        $this->otp = $otp;
    }

    public static function dispatch(string $type, string $identifier, string $otp, ?Container $container = null): bool
    {
        $container = $container ?? Container::getInstance();
        $job = new self($type, $identifier, $otp);

        // Try pushing to Redis queue
        if ($container->has(\MediTrack\Core\Queue::class)) {
            $queue = $container->get(\MediTrack\Core\Queue::class);
            $pushed = $queue->push('notifications', [
                'job' => self::class,
                'data' => [
                    'type' => $type,
                    'identifier' => $identifier,
                    'otp' => $otp,
                ],
            ]);
            if ($pushed) {
                return true;
            }
        }

        // If Queue is unavailable (e.g. redis down or in testing), execute synchronously
        return $job->handle($container);
    }

    public function handle(Container $container): bool
    {
        if ($this->type === 'phone') {
            $smsProvider = $container->has(SmsProviderInterface::class)
                ? $container->get(SmsProviderInterface::class)
                : new \MediTrack\Services\Sms\LogProvider();

            $brandName = brand('app.name', 'MediTrack');
            $message = "Your {$brandName} verification code is {$this->otp}. Valid for 5 minutes.";
            return $smsProvider->send($this->identifier, $message);
        }

        if ($this->type === 'email') {
            $emailService = $container->has(EmailService::class)
                ? $container->get(EmailService::class)
                : new EmailService();

            return $emailService->sendOtp($this->identifier, $this->otp);
        }

        return false;
    }
}

