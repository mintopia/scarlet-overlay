<?php

namespace App\Support;

use Illuminate\Support\Str;

class SlugGenerator
{
    /**
     * Generate a unique slug for the given model.
     */
    public static function unique(string $modelClass, string $text, string $column = 'slug'): string
    {
        $base = Str::slug($text);
        $slug = $base;
        $counter = 1;

        while ($modelClass::where($column, $slug)->exists()) {
            $counter++;
            $slug = $base.'-'.$counter;
        }

        return $slug;
    }
}
