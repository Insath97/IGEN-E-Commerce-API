<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'page',
        'section',
        'key',
        'value',
        'type',
        'label',
    ];

    /**
     * Scope to filter by page and section.
     */
    public function scopePageContent($query, string $page)
    {
        return $query->where('page', $page);
    }

    /**
     * Helper to get structured content for a page.
     */
    public static function getStructuredPageContent(string $page)
    {
        $contents = self::where('page', $page)->get();
        
        $structured = [];
        foreach ($contents as $content) {
            $structured[$content->section][$content->key] = $content->value;
        }

        return $structured;
    }
}
