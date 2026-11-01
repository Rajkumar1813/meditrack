<?php

declare(strict_types=1);

namespace MediTrack\Services\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use Monolog\Logger;
use MediTrack\Core\View;

class EmailService
{
    private ?Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    public function sendOtp(string $toEmail, string $otp): bool
    {
        $brandName = brand('app.name', 'MediTrack');
        $subject = "Your {$brandName} Verification Code: {$otp}";
        $htmlBody = View::render('emails/otp', [
            'otp' => $otp,
            'brandName' => $brandName,
        ]);

        return $this->send($toEmail, $subject, $htmlBody);
    }

    public function send(string $toEmail, string $subject, string $htmlBody): bool
    {
        $env = $_ENV['APP_ENV'] ?? 'local';

        // In testing or if no mail host configured, log and return true
        if ($env === 'testing') {
            if ($this->logger) {
                $this->logger->info("[EMAIL MOCK] To: {$toEmail} | Subject: {$subject}");
            }
            return true;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? '127.0.0.1';
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 1025); // 1025 is mailpit
            $mail->SMTPAuth = !empty($_ENV['MAIL_USERNAME']);
            if ($mail->SMTPAuth) {
                $mail->Username = $_ENV['MAIL_USERNAME'];
                $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            }

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@meditrack.example',
                $_ENV['MAIL_FROM_NAME'] ?? brand('app.name', 'MediTrack')
            );
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (MailException $e) {
            if ($this->logger) {
                $this->logger->error("Failed sending email to {$toEmail}: " . $e->getMessage());
            }
            return false;
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error("Unexpected error sending email to {$toEmail}: " . $e->getMessage());
            }
            return false;
        }
    }
}

