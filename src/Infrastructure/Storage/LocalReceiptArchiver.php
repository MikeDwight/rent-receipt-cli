<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Storage;

use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptResult;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;

final class LocalReceiptArchiver implements ReceiptArchiverInterface
{
    public function __construct(private readonly string $baseDir) {}

    public function archive(ArchiveReceiptRequest $request): ArchiveReceiptResult
    {
        $src = $request->localPdfPath;

        if (!is_file($src)) {
            return ArchiveReceiptResult::fail('pdf not found: ' . $src);
        }

        $dest = rtrim($this->baseDir, '/').'/'.ltrim($request->remotePath, '/');
        $dir = dirname($dest);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ArchiveReceiptResult::fail('cannot create dir: ' . $dir);
        }

        if (!@copy($src, $dest)) {
            return ArchiveReceiptResult::fail('copy failed to: ' . $dest);
        }

        return ArchiveReceiptResult::ok($dest);
    }
}
