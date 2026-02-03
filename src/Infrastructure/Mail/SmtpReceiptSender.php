<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Mail;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptResult;
use RentReceiptCli\Core\Service\ReceiptSenderInterface;

final class SmtpReceiptSender implements ReceiptSenderInterface
{
    /**
     * @param array{
     *   host:string, port:int, username:string, password:string,
     *   encryption:string, from_email:string, from_name:string
     * } $config
     */
    public function __construct(private readonly array $config) {}

    public function send(SendReceiptRequest $request): SendReceiptResult
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) $this->config['host'];
            $mail->Port = (int) $this->config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = (string) $this->config['username'];
            $mail->Password = (string) $this->config['password'];

            $enc = (string) ($this->config['encryption'] ?? 'tls');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom((string) $this->config['from_email'], (string) $this->config['from_name']);
            $mail->addAddress($request->toEmail, $request->toName);

            $mail->Subject = $request->subject;
            $mail->Body = $request->bodyText;
            $mail->AltBody = $request->bodyText;

            if (!is_file($request->pdfPath)) {
                return SendReceiptResult::fail('pdf not found: ' . $request->pdfPath);
            }
            $mail->addAttachment($request->pdfPath);

            $mail->send();
            return SendReceiptResult::ok();
        } catch (MailException $e) {
            return SendReceiptResult::fail($e->getMessage());
        }
    }
}
