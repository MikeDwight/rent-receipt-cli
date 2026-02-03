<?php
// src/Core/Service/ReceiptSenderInterface.php

namespace RentReceiptCli\Core\Service;

use RentReceiptCli\Core\Service\Dto\SendReceiptRequest;
use RentReceiptCli\Core\Service\Dto\SendReceiptResult;

interface ReceiptSenderInterface
{
    public function send(SendReceiptRequest $request): SendReceiptResult;
}
