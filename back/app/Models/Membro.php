<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membro extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome','sexo','concurso','cargo_efetivo','titularidade','email_pessoal','cargo_especial',
        'telefone_unidade','telefone_celular','unidade_lotacao','comarca_lotacao','time_extraprofissionais',
        'quantidade_filhos','nomes_filhos','estado_origem','academico','pretensao_carreira','carreira_anterior',
        'lideranca','grupos_identitarios'
    ];

    public function amigos()
    {
        return $this->belongsToMany(Membro::class, 'membro_amigos', 'membro_id', 'amigo_id')->withTimestamps();
    }
} 