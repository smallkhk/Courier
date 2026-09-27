<?php

namespace App\Models;

use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $guarded = ['id', 'user_id', 'business_id'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function oneLine(): string
    {
        return collect([$this->line1, $this->line2, $this->city, trim($this->region.' '.$this->postal_code), Countries::name($this->country_code)])->filter()->implode(', ');
    }

    /** Shape used by the address picker / booking form. */
    public function toPicker(): array
    {
        return [
            'line1' => $this->line1, 'line2' => $this->line2, 'city' => $this->city, 'region' => $this->region,
            'postal_code' => $this->postal_code, 'country' => $this->country_code, 'lat' => $this->lat, 'lng' => $this->lng,
            'place_id' => $this->place_id, 'contact_name' => $this->contact_name, 'phone' => $this->phone, 'email' => $this->email,
        ];
    }
}
