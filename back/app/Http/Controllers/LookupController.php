<?php

namespace App\Http\Controllers;

use App\Models\Lookup;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LookupController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type');
        $perPage = (int) $request->get('per_page', 50);
        $query = Lookup::query();
        if ($type) $query->where('type', $type);
        $query->orderBy('type')->orderBy('name');
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['required', 'string'],
        ]);
        $lookup = Lookup::firstOrCreate($validated, []);
        return response()->json($lookup, 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'name' => ['required', 'string'],
        ]);
        $lookup = Lookup::findOrFail($id);
        $exists = Lookup::where('type', $validated['type'])->where('name', $validated['name'])->where('id', '!=', $id)->exists();
        if ($exists) throw ValidationException::withMessages(['name' => ['Já existe um registro com esse tipo e nome.']]);
        $lookup->update($validated);
        return response()->json($lookup);
    }

    public function destroy(int $id)
    {
        $lookup = Lookup::findOrFail($id);
        $lookup->delete();
        return response()->json(['deleted' => true]);
    }

    public function bootstrapFromReports()
    {
        $map = [
            // 'Membro' => 'membro', // removido: membros são geridos na tela própria
            'Concurso' => 'concurso',
            'Naturalidade' => 'naturalidade',
            'Titularidade' => 'titularidade',
            'Cargo efetivo' => 'cargo_efetivo',
            'Cargo Especial' => 'cargo_especial',
            'Comarca Lotação' => 'comarca_lotacao',
            'Unidade Lotação' => 'unidade_lotacao',
        ];

        $inserted = 0;
        foreach ($map as $field => $type) {
            $values = Report::query()
                ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(data, ?)) as v', ['$."' . $field . '"'])
                ->whereNotNull('data')
                ->pluck('v')
                ->filter()
                ->map(fn($v) => trim((string)$v))
                ->filter()
                ->unique()
                ->values();

            foreach ($values as $name) {
                $exists = Lookup::where('type', $type)->where('name', $name)->exists();
                if (!$exists) {
                    Lookup::create(['type' => $type, 'name' => $name]);
                    $inserted++;
                }
            }
        }

        return response()->json(['inserted' => $inserted]);
    }
} 