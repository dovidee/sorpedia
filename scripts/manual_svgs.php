<?php
// Writes the step drawings from scripts/manuals.php to the guides already in the database: php scripts/manual_svgs.php
// Only manual_steps.svg changes; guides, steps, texts and PDFs stay as they are.

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
define('MANUALS_SVG_ONLY', true);
$n = require __DIR__ . '/manuals.php';
echo "$n guides updated\n";
