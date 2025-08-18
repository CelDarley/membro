<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Selo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'selos';

    protected $fillable = [
        'codigo',
        'descricao',
        'validade'
    ];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at'
    ];
}
