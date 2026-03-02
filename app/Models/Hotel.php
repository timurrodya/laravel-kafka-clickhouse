<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $table = 'hotels';

    protected $fillable = [
        'name',
        'address',
        'city',
    ];

    protected $casts = [];

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class, 'hotel_id');
    }
}
