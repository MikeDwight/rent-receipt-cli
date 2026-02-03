<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Storage;

use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptResult;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;

final class FallbackArchiver implements ReceiptArchiverInterface
{
    public function __construct(
        private readonly ReceiptArchiverInterface $primary,
        private readonly ReceiptArchiverInterface $fallback,
    ) {}

    public function archive(ArchiveReceiptRequest $request): ArchiveReceiptResult
    {
        $res = $this->primary->archive($request);
        if ($res->success) {
            return $res;
        }
        return $this->fallback->archive($request);
    }
}
