<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'billing_cycle',
        'amount',
        'status',
        'start_date',
        'end_date',
        'payment_method',
        'payment_id',
        'payment_data',
    ];

    protected $casts = [
        'payment_data' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && $this->end_date >= Carbon::today();
    }

    public function isExpired()
    {
        return $this->end_date < Carbon::today();
    }

    public function daysUntilExpiration()
    {
        return Carbon::today()->diffInDays($this->end_date, false);
    }
}
