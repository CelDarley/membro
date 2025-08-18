<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratoTipo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contrato_tipos';

    protected $fillable = [
        'codigo',
        'descricao'
    ];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at'
    ];
}
