<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

/**
 * A full copy of the database as a gzipped SQL file, in
 * storage/app/private/backups — outside the web root, so never downloadable
 * from the site. Written with PHP alone, so it also works on shared hosting
 * where mysqldump cannot be run. The newest KEEP copies are kept.
 *
 * Restore: gunzip the file and import it (phpMyAdmin → Import, or
 * `mysql dbname < file.sql`).
 */
class DatabaseBackup
{
    public const KEEP = 14;

    /** Rows per INSERT statement. */
    private const CHUNK = 200;

    /** Tables whose rows are not worth keeping (they rebuild themselves). */
    private const SKIP_ROWS = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public static function directory(): string
    {
        return storage_path('app/private/backups');
    }

    /** Writes a new backup; returns its path. */
    public static function run(): string
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Backups are written for MySQL / MariaDB only.');
        }

        File::ensureDirectoryExists(self::directory(), 0700);
        $path = self::directory().'/'.config('database.connections.mysql.database').'-'.now()->format('Ymd-His').'.sql.gz';
        $gz = gzopen($path, 'wb6');
        if (! $gz) {
            throw new RuntimeException("Cannot write {$path}.");
        }

        try {
            gzwrite($gz, "-- Backup of ".config('app.name').' taken '.now()->toIso8601String()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$table]) {
                $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

                if (in_array($table, self::SKIP_ROWS, true)) {
                    continue;
                }

                $rows = $pdo->query('SELECT * FROM `'.$table.'`', PDO::FETCH_ASSOC);
                $batch = [];
                foreach ($rows as $row) {
                    $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                    if (count($batch) === self::CHUNK) {
                        gzwrite($gz, self::insert($table, array_keys($row), $batch));
                        $batch = [];
                    }
                }
                if ($batch) {
                    gzwrite($gz, self::insert($table, array_keys($row), $batch));
                }
                gzwrite($gz, "\n");
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($gz);
        }

        @chmod($path, 0600);
        self::prune();
        Cache::forever('backup.last_at', now()->timestamp);

        return $path;
    }

    private static function insert(string $table, array $columns, array $batch): string
    {
        return 'INSERT INTO `'.$table.'` (`'.implode('`,`', $columns).'`) VALUES'."\n".implode(",\n", $batch).";\n";
    }

    /** Keeps the newest KEEP backups. */
    public static function prune(): void
    {
        collect(File::glob(self::directory().'/*.sql.gz'))
            ->sortDesc()
            ->slice(self::KEEP)
            ->each(fn ($old) => File::delete($old));
    }

    /** For hosts with no cron: true when the last backup is a day old. */
    public static function due(): bool
    {
        return now()->timestamp - (int) Cache::get('backup.last_at', 0) > 86400;
    }

    /** The backups there are, newest first. @return array<int, array{name: string, size: int, at: string}> */
    public static function list(): array
    {
        return collect(File::glob(self::directory().'/*.sql.gz'))
            ->sortDesc()->values()
            ->map(fn ($f) => ['name' => basename($f), 'size' => filesize($f), 'at' => date('Y-m-d H:i', filemtime($f))])
            ->all();
    }
}
