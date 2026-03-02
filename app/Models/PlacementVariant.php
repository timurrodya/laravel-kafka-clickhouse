<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementVariant extends Model
{
    protected $table = 'placement_variants';

    public $timestamps = false;

    protected $fillable = [
        'placement_id',
        'adults',
        'children_ages',
    ];

    protected $casts = [
        'adults' => 'integer',
    ];

    /**
     * Нормализованная строка возрастов детей: отсортированные через запятую, например "5,10".
     */
    public static function normalizeChildrenAges(array $ages): string
    {
        $ages = array_map('intval', array_filter($ages));
        sort($ages);
        return implode(',', $ages);
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class, 'placement_id');
    }
}
