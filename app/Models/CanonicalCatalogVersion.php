<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanonicalCatalogVersion extends Model
{
    protected $fillable = ['version', 'action', 'actor', 'note', 'snapshot'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }
}
