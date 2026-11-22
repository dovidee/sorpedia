<?php
declare(strict_types=1);

// Database backup: one consistent dump of the whole database, gzip compressed, in data/backups/.
// Keeps the newest KEEP files and deletes older ones. Run by hand or nightly from docker/entrypoint.sh.
//   php scripts/backup.php
// Restore (replaces the current contents):
//   gunzip -c data/backups/sorpedia-YYYYMMDD-HHMMSS.sql.gz | mariadb -u USER -p DBNAME

require __DIR__ . '/../src/bootstrap.php';

const KEEP = 14;
$dir = APP_ROOT . '/data/backups';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Kan ikke lage $dir\n");
    exit(1);
}
$dump = trim((string) shell_exec('command -v mariadb-dump || command -v mysqldump'));
if ($dump === '') {
    fwrite(STDERR, "Fant verken mariadb-dump eller mysqldump\n");
    exit(1);
}

// The password goes in a private options file, never on the command line where other processes can read it
$c = config();
$opt = tempnam(sys_get_temp_dir(), 'sorpedia-db');
chmod($opt, 0600);
file_put_contents($opt, "[client]\nuser=\"{$c['db_user']}\"\npassword=\"{$c['db_pass']}\"\nhost=\"{$c['db_host']}\"\nport={$c['db_port']}\n");

$file = sprintf('%s/sorpedia-%s.sql.gz', $dir, date('Ymd-His'));
$tmp = "$file.part";
// --single-transaction: a consistent snapshot of InnoDB tables without locking the shop
$cmd = sprintf('%s --defaults-extra-file=%s --single-transaction --no-tablespaces --routines --triggers --default-character-set=utf8mb4 %s | gzip -9 > %s',
    escapeshellarg($dump), escapeshellarg($opt), escapeshellarg($c['db_name']), escapeshellarg($tmp));
// bash for pipefail, so a failed dump is not hidden by a successful gzip
exec('bash -c ' . escapeshellarg('set -o pipefail; ' . $cmd) . ' 2>&1', $out, $code);
unlink($opt);
if ($code !== 0 || !is_file($tmp) || filesize($tmp) < 100) {
    @unlink($tmp);
    Audit::ensure();
    Audit::log('drift', 'backup_fail', null, ['code' => $code], 0);
    fwrite(STDERR, "Sikkerhetskopien feilet (kode $code): " . implode(' ', $out) . "\n");
    exit(1);
}
rename($tmp, $file);
chmod($file, 0600);
Audit::ensure();
Audit::log('drift', 'backup_ok', basename($file), ['bytes' => filesize($file)], 0);

$all = glob("$dir/sorpedia-*.sql.gz") ?: [];
rsort($all);
foreach (array_slice($all, KEEP) as $old) {
    unlink($old);
}
printf("%s  %s\n", basename($file), number_format(filesize($file) / 1048576, 1, ',', ' ') . ' MB');
