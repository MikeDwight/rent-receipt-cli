<?php

declare(strict_types=1);

namespace RentReceiptCli\Infrastructure\Pdf;

use RentReceiptCli\Core\Service\Pdf\PdfOptions;
use RentReceiptCli\Core\Service\PdfGenerator;

final class WkhtmltopdfPdfGenerator implements PdfGenerator
{
    public function __construct(
        private string $wkhtmltopdfBinary = 'wkhtmltopdf',
        private bool $keepTempHtmlOnFailure = true,
        private ?string $tmpDir = null,
    ) {}

    public function generateFromHtml(string $html, string $outputPdfPath, ?PdfOptions $options = null): void
    {
        $options ??= new PdfOptions();

        $dir = dirname($outputPdfPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create directory: {$dir}");
        }

        $tmpBaseDir = $this->tmpDir ?? sys_get_temp_dir();
        if (!is_dir($tmpBaseDir)) {
            throw new \RuntimeException("Temp directory does not exist: {$tmpBaseDir}");
        }

        $tmpHtml = tempnam($tmpBaseDir, 'rr-html-');
        if ($tmpHtml === false) {
            throw new \RuntimeException('Unable to create temp file');
        }

        $tmpHtmlFile = $tmpHtml . '.html';
        @rename($tmpHtml, $tmpHtmlFile);

        $bytes = file_put_contents($tmpHtmlFile, $html);
        if ($bytes === false) {
            @unlink($tmpHtmlFile);
            throw new \RuntimeException('Unable to write temp HTML');
        }

        $args = [];

        if ($options->enableLocalFileAccess) {
            $args[] = '--enable-local-file-access';
        }

        $args[] = '--page-size';
        $args[] = $options->pageSize;

        $args[] = '--orientation';
        $args[] = $options->orientation;

        $args[] = '--margin-top';
        $args[] = $options->marginTopMm . 'mm';
        $args[] = '--margin-right';
        $args[] = $options->marginRightMm . 'mm';
        $args[] = '--margin-bottom';
        $args[] = $options->marginBottomMm . 'mm';
        $args[] = '--margin-left';
        $args[] = $options->marginLeftMm . 'mm';

        // Commande : on construit un string "safe" via escapeshellarg
        $cmdParts = [escapeshellcmd($this->wkhtmltopdfBinary)];
        foreach ($args as $a) {
            $cmdParts[] = escapeshellarg($a);
        }
        $cmdParts[] = escapeshellarg('file://' . $tmpHtmlFile);
        $cmdParts[] = escapeshellarg($outputPdfPath);
        $cmd = implode(' ', $cmdParts);

        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            @unlink($tmpHtmlFile);
            throw new \RuntimeException('Unable to start wkhtmltopdf process');
        }

        // On n'écrit rien dans stdin
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        // Nettoyage du HTML temp si succès, ou si on ne veut pas le garder
        if ($exitCode === 0 || $this->keepTempHtmlOnFailure === false) {
            @unlink($tmpHtmlFile);
        }

        if ($exitCode !== 0) {
            $stderr = trim((string) $stderr);
            $stdout = trim((string) $stdout);

            // Eviter les erreurs énormes
            $stderrShort = mb_substr($stderr, 0, 2000);
            $stdoutShort = mb_substr($stdout, 0, 2000);

            $hint = $this->keepTempHtmlOnFailure
                ? "Temp HTML kept for debugging: {$tmpHtmlFile}"
                : "Temp HTML removed.";

            $details = [];
            if ($stderrShort !== '') {
                $details[] = "stderr: {$stderrShort}";
            }
            if ($stdoutShort !== '') {
                $details[] = "stdout: {$stdoutShort}";
            }

            $detailsText = $details !== [] ? ("\n" . implode("\n", $details)) : '';

            throw new \RuntimeException(
                "wkhtmltopdf failed (exit {$exitCode}). {$hint}\ncmd: {$cmd}{$detailsText}"
            );
        }

        if (!is_file($outputPdfPath) || filesize($outputPdfPath) === 0) {
            throw new \RuntimeException("PDF not created or empty: {$outputPdfPath}");
        }
    }
}
