<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Mail;

use RentReceiptCli\Core\Service\ReceiptSenderInterface;
use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptResult;

final class NullReceiptSender implements ReceiptSenderInterface
{
    public function send(SendReceiptRequest $request): SendReceiptResult
    {
        return SendReceiptResult::fail('sender not implemented yet');
    }
}
