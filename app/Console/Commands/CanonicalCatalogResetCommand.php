<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CanonicalCatalog;
use App\Support\CanonicalBaseline;
use Illuminate\Console\Command;

class CanonicalCatalogResetCommand extends Command
{
    protected $signature = 'metrics:catalog:reset {--force : Skip confirmation}';

    protected $description = 'Reset the canonical catalog to the code-defined baseline';

    public function handle(CanonicalCatalog $catalog): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset the canonical catalog to baseline? Current edits will be replaced.')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $version = $catalog->applyBaseline(
            CanonicalBaseline::definitions(),
            action: 'reset',
            actor: 'cli',
        );

        $this->info("Catalog reset to baseline. Active version: {$version}.");

        return self::SUCCESS;
    }
}
