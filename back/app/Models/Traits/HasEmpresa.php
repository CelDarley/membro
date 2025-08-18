<?php

namespace App\Models\Traits;

trait HasEmpresa
{
    public function empresa()
    {
        return $this->belongsTo(\App\Models\Empresa::class);
    }
}
