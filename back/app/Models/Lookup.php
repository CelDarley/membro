<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lookup extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
    ];

    protected $casts = [
        'type' => 'string',
        'name' => 'string',
    ];
} 