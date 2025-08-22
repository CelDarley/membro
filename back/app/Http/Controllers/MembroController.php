<?php

namespace App\Http\Controllers;

use App\Models\Membro;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class MembroController extends Controller
{
    private function labelToColumn(string $label): ?string
    {
        $map = [
            'Membro' => 'nome',
            'Nome' => 'nome',
            'Sexo' => 'sexo',
            'Concurso' => 'concurso',
            'Cargo efetivo' => 'cargo_efetivo',
            'Titularidade' => 'titularidade',
            'eMail pessoal' => 'email_pessoal',
            'Cargo Especial' => 'cargo_especial',
            'Telefone Unidade' => 'telefone_unidade',
            'Telefone celular' => 'telefone_celular',
            'Unidade Lotação' => 'unidade_lotacao',
            'Comarca Lotação' => 'comarca_lotacao',
            'Time de futebol e outros grupos extraprofissionais' => 'time_extraprofissionais',
            'Quantidade de filhos' => 'quantidade_filhos',
            'Nome dos filhos' => 'nomes_filhos',
            'Estado de origem' => 'estado_origem',
            'Acadêmico' => 'academico',
            'Pretensão de movimentação na carreira' => 'pretensao_carreira',
            'Carreira anterior' => 'carreira_anterior',
            'Liderança' => 'lideranca',
            'Grupos identitários' => 'grupos_identitarios',
        ];
        return $map[$label] ?? null;
    }

    private function toLabels(Membro $m): array
    {
        return [
            'Membro' => $m->nome,
            'Sexo' => $m->sexo,
            'Concurso' => $m->concurso,
            'Cargo efetivo' => $m->cargo_efetivo,
            'Titularidade' => $m->titularidade,
            'eMail pessoal' => $m->email_pessoal,
            'Cargo Especial' => $m->cargo_especial,
            'Telefone Unidade' => $m->telefone_unidade,
            'Telefone celular' => $m->telefone_celular,
            'Unidade Lotação' => $m->unidade_lotacao,
            'Comarca Lotação' => $m->comarca_lotacao,
            'Time de futebol e outros grupos extraprofissionais' => $m->time_extraprofissionais,
            'Quantidade de filhos' => $m->quantidade_filhos,
            'Nome dos filhos' => $m->nomes_filhos,
            'Estado de origem' => $m->estado_origem,
            'Acadêmico' => $m->academico,
            'Pretensão de movimentação na carreira' => $m->pretensao_carreira,
            'Carreira anterior' => $m->carreira_anterior,
            'Liderança' => $m->lideranca,
            'Grupos identitários' => $m->grupos_identitarios,
            // Campo técnico usado no frontend (array de IDs)
            'Amigos no MP (IDs)' => $m->relationLoaded('amigos') ? $m->amigos->pluck('id')->values()->all() : [],
        ];
    }

    private function applyFiltersFromJson($query, ?string $filtersJson): void
    {
        if (!$filtersJson) return;
        $arr = json_decode($filtersJson, true);
        if (!is_array($arr)) return;
        foreach ($arr as $label => $values) {
            if (!is_array($values) || empty($values)) continue;
            $col = $this->labelToColumn((string)$label);
            if (!$col) continue;
            $vals = array_values(array_filter(array_map('strval', $values), fn($v)=>$v!==''));
            if (!empty($vals)) $query->whereIn($col, $vals);
        }
    }

    private function mapInputData(array $data): array
    {
        $payload = [];
        foreach ($data as $label => $value) {
            $col = $this->labelToColumn((string)$label);
            if (!$col) continue;
            if ($col === 'quantidade_filhos') $value = ($value === '' || $value === null) ? null : (int)$value;
            if ($col === 'estado_origem') $value = $value ? substr((string)$value, 0, 2) : null;
            $payload[$col] = $value;
        }
        return $payload;
    }

    public function index(Request $request)
    {
        $q = (string) $request->get('q', '');
        $perPage = (int) $request->get('per_page', 20);
        $query = Membro::query()->with(['amigos:id']);

        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('nome','like',"%{$q}%")
                  ->orWhere('comarca_lotacao','like',"%{$q}%")
                  ->orWhere('cargo_efetivo','like',"%{$q}%")
                  ->orWhere('titularidade','like',"%{$q}%");
            });
        }

        // filtros simples via ?filters[field][]=val
        $filters = $request->input('filters', []);
        if (is_array($filters)) {
            foreach ($filters as $field => $values) {
                if (!is_array($values) || empty($values)) continue;
                $values = array_values(array_filter(array_map('strval', $values), fn($v)=>$v!==''));
                if (!empty($values)) {
                    $query->whereIn($field, $values);
                }
            }
        }
        // filtros via labels (compatibilidade com frontend)
        $this->applyFiltersFromJson($query, $request->get('filters_json'));

        $paginated = $query->orderByDesc('id')->paginate($perPage);
        $mapped = $paginated->getCollection()->map(fn(Membro $m) => [
            'id' => $m->id,
            'data' => $this->toLabels($m),
        ]);
        $paginated->setCollection($mapped);
        return response()->json($paginated);
    }

    public function aggregate(Request $request)
    {
        $fieldLabel = (string) $request->get('field', '');
        if ($fieldLabel === '') throw ValidationException::withMessages(['field'=>['Obrigatório']]);
        $field = $this->labelToColumn($fieldLabel) ?? $fieldLabel; // fallback

        $base = Membro::query();
        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where(function($w) use ($q) {
                $w->where('nome','like',"%{$q}%")
                  ->orWhere('comarca_lotacao','like',"%{$q}%")
                  ->orWhere('cargo_efetivo','like',"%{$q}%");
            });
        }
        // aplicar filtros compatíveis
        $this->applyFiltersFromJson($base, $request->get('filters_json'));

        $limit = (int) $request->get('limit', 50);
        $rows = $base->selectRaw("`{$field}` as v, COUNT(*) as c")
                     ->groupBy('v')->orderByDesc('c')->limit($limit)->get();
        $result = $rows->filter(fn($r)=>($r->v !== null && trim((string)$r->v) !== ''))->values();
        return response()->json(['field'=>$fieldLabel,'data'=>$result]);
    }

    public function stats(Request $request)
    {
        $base = Membro::query();
        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where('nome','like',"%{$q}%");
        }
        $this->applyFiltersFromJson($base, $request->get('filters_json'));
        $total = $base->count();
        $female = (clone $base)->where('sexo','Feminino')->count();
        return response()->json([
            'total' => $total,
            'female_count' => $female,
            'female_pct' => $total ? round($female*100/$total,1) : 0.0,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->input('data', []);
        if (!is_array($data)) throw ValidationException::withMessages(['data'=>['Formato inválido']]);
        $payload = $this->mapInputData($data);
        DB::beginTransaction();
        try {
            $m = Membro::create($payload);
            $friendIds = $data['Amigos no MP (IDs)'] ?? [];
            if (is_array($friendIds)) {
                $ids = array_values(array_filter(array_map('intval', $friendIds), fn($n)=>$n>0 && $n !== $m->id));
                $m->amigos()->sync($ids);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return response()->json(['success'=>true,'id'=>$m->id]);
    }

    public function update(Request $request, int $id)
    {
        $m = Membro::findOrFail($id);
        $data = $request->input('data', []);
        if (!is_array($data)) throw ValidationException::withMessages(['data'=>['Formato inválido']]);
        $payload = $this->mapInputData($data);
        DB::beginTransaction();
        try {
            $m->update($payload);
            $friendIds = $data['Amigos no MP (IDs)'] ?? [];
            if (is_array($friendIds)) {
                $ids = array_values(array_filter(array_map('intval', $friendIds), fn($n)=>$n>0 && $n !== $m->id));
                $m->amigos()->sync($ids);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return response()->json(['success'=>true]);
    }
} 