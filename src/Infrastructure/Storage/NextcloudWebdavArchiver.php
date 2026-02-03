<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Storage;

use RentReceiptCli\Core\Service\Dto\ArchiveReceiptRequest;
use RentReceiptCli\Core\Service\Dto\ArchiveReceiptResult;
use RentReceiptCli\Core\Service\ReceiptArchiverInterface;

final class NextcloudWebdavArchiver implements ReceiptArchiverInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $basePath,
    ) {}

    public function archive(ArchiveReceiptRequest $request): ArchiveReceiptResult
    {
        if ($this->baseUrl === '' || $this->username === '' || $this->password === '') {
            return ArchiveReceiptResult::fail('nextcloud not configured');
        }

        $src = $request->localPdfPath;
        if (!is_file($src)) {
            return ArchiveReceiptResult::fail('pdf not found: ' . $src);
        }

        $remote = $this->buildRemoteUrl($request->remotePath);

        $data = @file_get_contents($src);
        if ($data === false) {
            return ArchiveReceiptResult::fail('cannot read pdf: ' . $src);
        }

        $ch = curl_init($remote);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/pdf',
            ],
        ]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            return ArchiveReceiptResult::fail('curl error: ' . $err);
        }

        if ($code < 200 || $code >= 300) {
            return ArchiveReceiptResult::fail('webdav put failed: http ' . $code);
        }

        return ArchiveReceiptResult::ok($remote);
    }

    private function buildRemoteUrl(string $remotePath): string
    {
        $base = rtrim($this->baseUrl, '/');
        $bp = '/' . trim($this->basePath, '/');
        $rp = '/' . ltrim($remotePath, '/');

        return $base . $bp . '/' . rawurlencode($this->username) . $rp;
    }
}
