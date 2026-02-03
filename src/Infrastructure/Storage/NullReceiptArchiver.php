<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Storage;

use RentReceiptCli\Core\Service\ReceiptArchiverInterface;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptResult;

final class NullReceiptArchiver implements ReceiptArchiverInterface
{
    public function archive(ArchiveReceiptRequest $request): ArchiveReceiptResult
    {
        return ArchiveReceiptResult::fail('archiver not implemented yet');
    }
}
