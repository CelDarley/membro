<?php

namespace App\Http\Controllers;

use App\Models\Membro;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MembroController extends Controller
{
    public function index(Request $request)
    {
        $q = (string) $request->get('q', '');
        $perPage = (int) $request->get('per_page', 20);
        $query = Membro::query();

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

        return response()->json($query->orderByDesc('id')->paginate($perPage));
    }

    public function aggregate(Request $request)
    {
        $field = (string) $request->get('field', '');
        if ($field === '') throw ValidationException::withMessages(['field'=>['Obrigatório']]);

        $base = Membro::query();
        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where(function($w) use ($q) {
                $w->where('nome','like',"%{$q}%")
                  ->orWhere('comarca_lotacao','like',"%{$q}%")
                  ->orWhere('cargo_efetivo','like',"%{$q}%");
            });
        }
        $filters = $request->input('filters', []);
        if (is_array($filters)) {
            foreach ($filters as $f => $values) {
                if (!is_array($values) || empty($values)) continue;
                $values = array_values(array_filter(array_map('strval', $values), fn($v)=>$v!==''));
                if (!empty($values)) $base->whereIn($f, $values);
            }
        }
        $limit = (int) $request->get('limit', 50);
        $rows = $base->selectRaw("`{$field}` as v, COUNT(*) as c")
                     ->groupBy('v')->orderByDesc('c')->limit($limit)->get();
        $result = $rows->filter(fn($r)=>($r->v !== null && trim((string)$r->v) !== ''))->values();
        return response()->json(['field'=>$field,'data'=>$result]);
    }

    public function stats(Request $request)
    {
        $base = Membro::query();
        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where('nome','like',"%{$q}%");
        }
        $filters = $request->input('filters', []);
        if (is_array($filters)) {
            foreach ($filters as $f => $values) {
                if (!is_array($values) || empty($values)) continue;
                $values = array_values(array_filter(array_map('strval', $values), fn($v)=>$v!==''));
                if (!empty($values)) $base->whereIn($f, $values);
            }
        }
        $total = $base->count();
        $female = Membro::where('sexo','Feminino')->count();
        return response()->json([
            'total' => $total,
            'female_count' => $female,
            'female_pct' => $total ? round($female*100/$total,1) : 0.0,
        ]);
    }
} 