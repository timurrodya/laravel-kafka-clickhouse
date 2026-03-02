<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceByDay extends Model
{
    protected $table = 'prices_by_day';

    public $timestamps = false;

    protected $fillable = [
        'placement_id',
        'date',
        'price',
        'currency',
    ];

    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:2',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class, 'placement_id');
    }
}
