<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CanonicalCatalog;
use Illuminate\Console\Command;

class CanonicalCatalogRollbackCommand extends Command
{
    protected $signature = 'metrics:catalog:rollback {version : Target version to restore} {--force : Skip confirmation}';

    protected $description = 'Roll the canonical catalog back to a prior version';

    public function handle(CanonicalCatalog $catalog): int
    {
        $target = (int) $this->argument('version');

        if (! $this->option('force') && ! $this->confirm("Roll the canonical catalog back to v{$target}?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $version = $catalog->rollback($target, actor: 'cli');

        $this->info("Rolled back to v{$target}. New active version: {$version}.");

        return self::SUCCESS;
    }
}
