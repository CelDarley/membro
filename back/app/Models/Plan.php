<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'monthly_price',
        'semiannual_price',
        'annual_price',
        'seals_limit',
        'contracts_limit',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'monthly_price' => 'decimal:2',
        'semiannual_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function getPriceForCycle($cycle)
    {
        return match($cycle) {
            'monthly' => $this->monthly_price,
            'semiannual' => $this->semiannual_price,
            'annual' => $this->annual_price,
            default => $this->monthly_price,
        };
    }

    public function isUnlimited()
    {
        return is_null($this->seals_limit) && is_null($this->contracts_limit);
    }
}
