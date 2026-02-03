<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Mail;

use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptResult;
use RentReceiptCli\Core\Service\ReceiptSenderInterface;

final class AlwaysOkReceiptSender implements ReceiptSenderInterface
{
    public function send(SendReceiptRequest $request): SendReceiptResult
    {
        return SendReceiptResult::ok();
    }
}
