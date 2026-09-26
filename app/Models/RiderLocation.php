<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderLocation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'received_at' => 'datetime', 'expires_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
    }
}
