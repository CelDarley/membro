<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Report::query();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where('search_text', 'like', "%{$q}%");
        }

        // Filtros por coluna
        $filters = [];
        if ($request->filled('filters_json')) {
            $decoded = json_decode($request->get('filters_json'), true);
            if (is_array($decoded)) $filters = $decoded;
        } elseif (is_array($request->input('filters'))) {
            $filters = $request->input('filters');
        }
        foreach ($filters as $field => $values) {
            if (!is_array($values) || empty($values)) continue;
            $values = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $values), static fn($v) => $v !== ''));
            if (empty($values)) continue;
            $jsonPath = '$."' . $field . '"';
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $bindings = array_merge([$jsonPath], $values);
            $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) IN ('.$placeholders.')', $bindings);
        }

        $perPage = (int) $request->get('per_page', 20);
        $reports = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($reports);
    }

    public function aggregate(Request $request)
    {
        $field = (string) $request->get('field', '');
        if ($field === '') {
            throw ValidationException::withMessages(['field' => ['O parâmetro field é obrigatório.']]);
        }

        $base = Report::query();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where('search_text', 'like', "%{$q}%");
        }

        $filters = [];
        if ($request->filled('filters_json')) {
            $decoded = json_decode($request->get('filters_json'), true);
            if (is_array($decoded)) $filters = $decoded;
        } elseif (is_array($request->input('filters'))) {
            $filters = $request->input('filters');
        }
        foreach ($filters as $f => $values) {
            if (!is_array($values) || empty($values)) continue;
            $values = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $values), static fn($v) => $v !== ''));
            if (empty($values)) continue;
            $jsonPath = '$."' . $f . '"';
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $bindings = array_merge([$jsonPath], $values);
            $base->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) IN ('.$placeholders.')', $bindings);
        }

        $jsonPath = '$."' . $field . '"';
        $limit = (int) $request->get('limit', 50);
        $rows = $base->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) as v, COUNT(*) as c', [$jsonPath])
            ->groupBy('v')
            ->orderByDesc('c')
            ->limit($limit)
            ->get();

        // Remove nulos/vazios do topo
        $result = $rows->filter(fn($r) => ($r->v !== null && trim((string)$r->v) !== ''))
            ->values();

        return response()->json(['field' => $field, 'data' => $result]);
    }

    public function aggregateByYear(Request $request)
    {
        $field = (string) $request->get('field', '');
        if ($field === '') {
            throw ValidationException::withMessages(['field' => ['O parâmetro field é obrigatório.']]);
        }

        $base = Report::query();
        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where('search_text', 'like', "%{$q}%");
        }
        // aplicar filtros já existentes
        $filters = [];
        if ($request->filled('filters_json')) {
            $decoded = json_decode($request->get('filters_json'), true);
            if (is_array($decoded)) $filters = $decoded;
        } elseif (is_array($request->input('filters'))) {
            $filters = $request->input('filters');
        }
        foreach ($filters as $f => $values) {
            if (!is_array($values) || empty($values)) continue;
            $values = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $values), static fn($v) => $v !== ''));
            if (empty($values)) continue;
            $jsonPath = '$."' . $f . '"';
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $bindings = array_merge([$jsonPath], $values);
            $base->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) IN ('.$placeholders.')', $bindings);
        }

        $all = $base->get(['data']);
        $counts = [];
        foreach ($all as $row) {
            $data = $row->data ?? [];
            $raw = (string)($data[$field] ?? '');
            $year = null;
            if (preg_match('/\b(\d{4})\b/', $raw, $m)) {
                $year = (int)$m[1];
            }
            if ($year) {
                $counts[$year] = ($counts[$year] ?? 0) + 1;
            }
        }
        ksort($counts);
        $result = [];
        foreach ($counts as $y => $c) { $result[] = ['year' => $y, 'count' => $c]; }
        return response()->json(['field' => $field, 'data' => $result]);
    }

    public function stats(Request $request)
    {
        $base = Report::query();
        if ($request->filled('q')) {
            $q = $request->string('q');
            $base->where('search_text', 'like', "%{$q}%");
        }
        // filtros
        $filters = [];
        if ($request->filled('filters_json')) {
            $decoded = json_decode($request->get('filters_json'), true);
            if (is_array($decoded)) $filters = $decoded;
        } elseif (is_array($request->input('filters'))) {
            $filters = $request->input('filters');
        }
        foreach ($filters as $f => $values) {
            if (!is_array($values) || empty($values)) continue;
            $values = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $values), static fn($v) => $v !== ''));
            if (empty($values)) continue;
            $jsonPath = '$."' . $f . '"';
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $bindings = array_merge([$jsonPath], $values);
            $base->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) IN ('.$placeholders.')', $bindings);
        }

        $all = $base->get(['data']);
        $total = $all->count();

        $female = 0;
        $ages = [];
        $tenures = [];
        $now = Carbon::now();
        foreach ($all as $row) {
            $data = $row->data ?? [];
            $sexo = (string)($data['Sexo'] ?? '');
            if (strcasecmp($sexo, 'Feminino') === 0) $female++;

            $dob = (string)($data['Data de nascimento'] ?? '');
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dob)) {
                try { $d = Carbon::createFromFormat('d/m/Y', $dob); $ages[] = $d->diffInYears($now); } catch (\Throwable $e) {}
            }

            $start = (string)($data['Data início na promotoria'] ?? '');
            if (!$start) { $start = (string)($data['Data inicio na promotoria'] ?? ''); }
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $start)) {
                try { $s = Carbon::createFromFormat('d/m/Y', $start); $tenures[] = round($s->diffInDays($now) / 365.25, 2); } catch (\Throwable $e) {}
            }
        }

        $median = function(array $arr) {
            if (empty($arr)) return null;
            sort($arr);
            $n = count($arr);
            $m = intdiv($n, 2);
            return $n % 2 ? $arr[$m] : round(($arr[$m-1] + $arr[$m]) / 2, 2);
        };

        $femalePct = $total ? round($female * 100 / $total, 1) : 0.0;
        $medianAge = $median($ages);
        $medianTenure = $median($tenures);

        return response()->json([
            'total' => $total,
            'female_count' => $female,
            'female_pct' => $femalePct,
            'median_age_years' => $medianAge,
            'median_tenure_years' => $medianTenure,
        ]);
    }

    public function show(int $id)
    {
        $report = Report::findOrFail($id);
        return response()->json($report);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        $data = $validated['data'];
        $searchText = strtolower(implode(' ', array_map(static fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $data)));
        $rowHash = hash('sha256', json_encode($data));

        try {
            $report = Report::create([
                'data' => $data,
                'search_text' => $searchText,
                'row_hash' => $rowHash,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw ValidationException::withMessages(['data' => ['Registro duplicado.']]);
            }
            throw $e;
        }

        return response()->json($report, 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        $report = Report::findOrFail($id);
        $data = $validated['data'];
        $searchText = strtolower(implode(' ', array_map(static fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $data)));
        $rowHash = hash('sha256', json_encode($data));

        try {
            $report->update([
                'data' => $data,
                'search_text' => $searchText,
                'row_hash' => $rowHash,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw ValidationException::withMessages(['data' => ['Registro duplicado.']]);
            }
            throw $e;
        }

        return response()->json($report);
    }

    public function destroy(int $id)
    {
        $report = Report::findOrFail($id);
        $report->delete();
        return response()->json(['deleted' => true]);
    }
} 