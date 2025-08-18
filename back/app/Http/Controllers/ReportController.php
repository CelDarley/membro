<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

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