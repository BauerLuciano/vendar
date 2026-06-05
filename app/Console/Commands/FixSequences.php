<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSequences extends Command
{
    protected $signature = 'db:fix-sequences
        {--database= : The database connection to use}
        {--table=* : Specific tables to fix (empty = all)}';

    protected $description = 'Fix PostgreSQL sequences after inserts with explicit IDs';

    public function handle(): int
    {
        $connection = $this->option('database') ?: config('database.default');

        if (DB::connection($connection)->getDriverName() !== 'pgsql') {
            $this->error('This command only works with PostgreSQL.');
            return Command::FAILURE;
        }

        $onlyTables = $this->option('table');

        $rows = DB::connection($connection)->select("
            SELECT
                c.relname AS table_name,
                a.attname AS column_name,
                pg_get_serial_sequence(c.relname, a.attname) AS sequence_name
            FROM pg_class c
            JOIN pg_attribute a ON a.attrelid = c.oid
            WHERE c.relkind = 'r'
                AND a.attnum > 0
                AND NOT a.attisdropped
                AND a.atttypid = ANY('{int8,int4,int2}'::regtype[])
                AND c.relname NOT LIKE 'pg_%'
                AND c.relname NOT LIKE 'sql_%'
                AND pg_get_serial_sequence(c.relname, a.attname) IS NOT NULL
            ORDER BY c.relname
        ");

        if (empty($rows)) {
            $this->warn('No serial sequences found.');
            return Command::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $table = $row->table_name;
            $column = $row->column_name;
            $sequence = $row->sequence_name;

            if (!empty($onlyTables) && !in_array($table, $onlyTables, true)) {
                $skipped++;
                continue;
            }

            $maxId = DB::connection($connection)
                ->table($table)
                ->max($column) ?? 0;

            $nextVal = max((int) $maxId + 1, 1);

            DB::connection($connection)
                ->statement("SELECT setval(?, ?)", [$sequence, $nextVal]);

            $this->line("  <info>✓</info> {$table}.{$column} → {$nextVal}");
            $fixed++;
        }

        $this->newLine();
        $this->info("Fixed {$fixed} sequence(s)" . ($skipped ? " ({$skipped} skipped)" : ""));

        return Command::SUCCESS;
    }
}
