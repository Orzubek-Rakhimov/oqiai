<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * The safety copy before a deploy. It runs on the server each time a new
 * version starts, before the migrations: when that version brings a
 * migration, the SQLite database is copied first with VACUUM INTO, which
 * makes a consistent copy even while the app is using the file. A deploy
 * that leaves the database as it is saves nothing, and so does the very
 * first one, on an empty database. Failing here stops the start-up, so the
 * migrations never run without their copy.
 */
#[Signature('backup:before-deploy')]
#[Description('Save a copy of the SQLite database before a deploy changes its structure')]
class BackupBeforeDeploy extends Command
{
    private const string PREFIX = 'before-deploy-';

    public function handle(Migrator $migrator): int
    {
        if (! $this->bringsAMigration($migrator)) {
            $this->line('No copy needed: this deploy does not change the database.');

            return self::SUCCESS;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            $this->error('The safety copy only knows SQLite, so nothing was changed.');

            return self::FAILURE;
        }

        $folder = (string) config('deploy.backups.path');
        File::ensureDirectoryExists($folder);
        $file = $folder.'/'.self::PREFIX.now()->format('Y-m-d-His').'.sqlite';

        try {
            $connection->statement('VACUUM INTO '.$connection->getPdo()->quote($file));
        } catch (Throwable $exception) {
            File::delete($file);
            $this->error('The copy of the database could not be made, so nothing was changed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database saved to '.$file.'.');
        $this->keepOnlyTheNewest($folder);

        return self::SUCCESS;
    }

    /**
     * Whether this version has a migration the database has not run yet.
     * An empty database has nothing worth saving.
     */
    private function bringsAMigration(Migrator $migrator): bool
    {
        if (! $migrator->repositoryExists()) {
            return false;
        }

        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);

        return array_diff(array_keys($files), $migrator->getRepository()->getRan()) !== [];
    }

    /**
     * The copies are named by their time, so the newest sort last.
     */
    private function keepOnlyTheNewest(string $folder): void
    {
        $copies = collect(File::glob($folder.'/'.self::PREFIX.'*.sqlite'))->sort()->values();

        $copies->slice(0, max(0, $copies->count() - (int) config('deploy.backups.keep')))
            ->each(fn (string $old) => File::delete($old));
    }
}
