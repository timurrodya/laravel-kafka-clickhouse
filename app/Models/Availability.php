<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    protected $table = 'availability';

    public $timestamps = false;

    protected $fillable = [
        'placement_id',
        'date',
        'available',
    ];

    protected $casts = [
        'date' => 'date',
        'available' => 'boolean',
    ];

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class, 'placement_id');
    }
}
