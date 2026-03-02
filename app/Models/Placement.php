<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Placement extends Model
{
    protected $table = 'placements';

    protected $fillable = [
        'hotel_id',
        'name',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PlacementVariant::class, 'placement_id');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(Availability::class, 'placement_id');
    }

    public function pricesByDay(): HasMany
    {
        return $this->hasMany(PriceByDay::class, 'placement_id');
    }
}
