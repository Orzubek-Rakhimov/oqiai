<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
| On the server the database is one SQLite file on a volume. Before a deploy
| runs a new migration, the start-up script saves a consistent copy of it, so
| a bad change can be undone without last night's backup.
*/

beforeEach(function () {
    $this->folder = storage_path('framework/testing/deploy-'.uniqid());
    File::ensureDirectoryExists($this->folder);
    touch($this->folder.'/database.sqlite');

    config([
        'database.connections.server' => [
            ...config('database.connections.sqlite'),
            'database' => $this->folder.'/database.sqlite',
        ],
        // Back to the test database in afterEach, which RefreshDatabase rolls back.
        'database.default' => 'server',
        'deploy.backups.path' => $this->folder.'/before-deploy',
        'deploy.backups.keep' => 2,
    ]);

    $this->artisan('migrate', ['--force' => true]);
});

afterEach(function () {
    DB::purge('server');
    config(['database.default' => 'sqlite']);
    File::deleteDirectory($this->folder);
});

function copiesIn(string $folder): array
{
    return File::glob($folder.'/before-deploy/*.sqlite');
}

function aNewMigrationArrives(): void
{
    app('migrator')->path(base_path('tests/Fixtures/migrations'));
}

test('a deploy that brings a migration saves the database as it was first', function () {
    User::factory()->create(['phone' => '+998901234567']);
    aNewMigrationArrives();

    $this->artisan('backup:before-deploy')->assertSuccessful();

    expect(copiesIn($this->folder))->toHaveCount(1);

    $copy = new PDO('sqlite:'.copiesIn($this->folder)[0]);
    expect($copy->query('select phone from users')->fetchAll(PDO::FETCH_COLUMN))->toBe(['+998901234567']);
});

test('a deploy that leaves the database as it is saves nothing', function () {
    $this->artisan('backup:before-deploy')
        ->expectsOutputToContain('No copy needed')
        ->assertSuccessful();

    expect(copiesIn($this->folder))->toBe([]);
});

test('only the newest copies are kept', function () {
    aNewMigrationArrives();

    foreach (['2026-11-01 10:00', '2026-11-02 10:00', '2026-11-03 10:00'] as $deployedAt) {
        $this->travelTo($deployedAt);
        $this->artisan('backup:before-deploy')->assertSuccessful();
    }

    expect(array_map('basename', copiesIn($this->folder)))->toBe([
        'before-deploy-2026-11-02-100000.sqlite',
        'before-deploy-2026-11-03-100000.sqlite',
    ]);
});
