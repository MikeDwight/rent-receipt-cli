<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Storage;

use RentReceiptCli\Application\Port\Logger;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptResult;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;

final class FallbackArchiver implements ReceiptArchiverInterface
{
    public function __construct(
        private readonly ReceiptArchiverInterface $primary,
        private readonly ReceiptArchiverInterface $fallback,
        private readonly ?Logger $logger = null,
    ) {}

    public function archive(ArchiveReceiptRequest $request): ArchiveReceiptResult
    {
        $res = $this->primary->archive($request);

        if ($res->success) {
            $this->logger?->info('archive.primary.success', [
                'local_pdf' => $request->localPdfPath,
                'remote_path' => $request->remotePath,
                'archived_path' => $res->archivedPath,
            ]);
            return $res;
        }

        $this->logger?->warning('archive.primary.failed_fallback', [
            'local_pdf' => $request->localPdfPath,
            'remote_path' => $request->remotePath,
            'error' => $res->errorMessage,
        ]);

        $fallbackRes = $this->fallback->archive($request);

        if ($fallbackRes->success) {
            $this->logger?->info('archive.fallback.success', [
                'local_pdf' => $request->localPdfPath,
                'remote_path' => $request->remotePath,
                'archived_path' => $fallbackRes->archivedPath,
            ]);
        } else {
            $this->logger?->error('archive.fallback.failed', [
                'local_pdf' => $request->localPdfPath,
                'remote_path' => $request->remotePath,
                'error' => $fallbackRes->errorMessage,
            ]);
        }

        return $fallbackRes;
    }
}
