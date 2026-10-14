<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    /** Contract limit (ADDR-02 ADDRESS_LIMIT_REACHED). */
    public const MAX_PER_USER = 10;

    protected $fillable = [
        'label',
        'recipient_name',
        'phone',
        'address_line',
        'province_id',
        'province_name',
        'city_id',
        'city_name',
        'district_id',
        'district_name',
        'subdistrict_id',
        'subdistrict_name',
        'post_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
