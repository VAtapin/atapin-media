<?php

namespace App\Console\Commands;

use App\Services\Access;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetProjectData extends Command
{
    protected $signature = 'platform:reset-data
        {--force : Allow destructive deletion}
        {--confirm= : Must be RESET-ALL-PROJECT-DATA}';

    protected $description = 'Delete all project data while preserving the database schema and Takeout files';

    public function handle(): int
    {
        if (! $this->option('force') || $this->option('confirm') !== 'RESET-ALL-PROJECT-DATA') {
            $this->error('Refusing to reset data. Use --force --confirm=RESET-ALL-PROJECT-DATA.');
            return self::FAILURE;
        }

        $tables = $this->tables()->reject(fn (string $table) => $table === 'migrations' || str_starts_with($table, 'sqlite_'));
        if ($tables->isEmpty()) {
            $this->info('No project tables found to reset.');
            return self::SUCCESS;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if ($driver === 'sqlite') DB::statement('PRAGMA foreign_keys=OFF');
        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
                $this->line('Cleared '.$table);
            }
        } finally {
            if ($driver === 'mysql') DB::statement('SET FOREIGN_KEY_CHECKS=1');
            if ($driver === 'sqlite') DB::statement('PRAGMA foreign_keys=ON');
        }

        app(Access::class)->seed();
        $this->info('Project data reset. The database schema and migrations were preserved.');
        $this->info('Create a new administrator with: php artisan platform:owner');
        return self::SUCCESS;
    }

    private function tables(): \Illuminate\Support\Collection
    {
        return match (DB::getDriverName()) {
            'mysql' => collect(DB::select('SHOW TABLES'))->map(fn ($row) => (string) array_values((array) $row)[0]),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table'"))->map(fn ($row) => (string) $row->name),
            default => throw new \RuntimeException('Unsupported database driver for project reset.'),
        };
    }
}
