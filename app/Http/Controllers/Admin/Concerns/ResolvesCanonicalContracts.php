<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\CanonicalCatalog;
use App\Services\CanonicalReader;

trait ResolvesCanonicalContracts
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function resolveContracts(CanonicalReader $reader, CanonicalCatalog $catalog): array
    {
        if (! config('scarlet.canonical.enabled')) {
            return [];
        }

        $keys = array_keys($catalog->all());

        return array_filter($reader->readMany($keys), fn ($c) => $c !== null);
    }
}
