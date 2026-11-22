<?php
// Prints the assembly guide PDFs again without rebuilding the database: php scripts/manual_pdfs.php
// Needs headless Chrome or Chromium (CHROME_BIN or on the PATH).

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
define('MANUALS_PDF_ONLY', true);
require_once __DIR__ . '/manual_pdf.php';
if (manual_pdf_browser() === '') {
    fwrite(STDERR, "No headless Chrome or Chromium found. Set CHROME_BIN.\n");
    exit(1);
}
$pdfFailed = []; // filled by manuals.php
$n = require __DIR__ . '/manuals.php';
if ($pdfFailed) {
    fwrite(STDERR, count($pdfFailed) . ' PDF(s) failed to print: ' . implode(', ', $pdfFailed) . "\n");
    exit(1);
}
echo "$n guides printed to public/media/manuals\n";
