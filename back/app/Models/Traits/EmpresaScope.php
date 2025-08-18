<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait EmpresaScope
{
    protected static function bootEmpresaScope()
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (Auth::check() && Auth::user()->empresa_id) {
                $builder->where('empresa_id', Auth::user()->empresa_id);
            }
        });
    }
}
