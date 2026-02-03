<?php
// src/Core/Service/ReceiptArchiverInterface.php

namespace RentReceiptCli\Core\Service;

use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptResult;

interface ReceiptArchiverInterface
{
    public function archive(ArchiveReceiptRequest $request): ArchiveReceiptResult;
}
