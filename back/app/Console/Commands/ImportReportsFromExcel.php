<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Report;
use Illuminate\Support\Facades\DB;

class ImportReportsFromExcel extends Command
{
    protected $signature = 'reports:import {path? : Caminho para o arquivo Excel} {--fresh : Limpa a tabela antes de importar}';

    protected $description = 'Importa registros do arquivo Excel para a tabela reports';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('promo.xls');

        if (!file_exists($path)) {
            $this->error("Arquivo não encontrado: {$path}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Report::truncate();
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Throwable $e) {
            $this->error('Falha ao ler o Excel: ' . $e->getMessage());
            return self::FAILURE;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            $this->warn('Planilha vazia.');
            return self::SUCCESS;
        }

        // Detecta automaticamente a linha de cabeçalho:
        // Critérios: primeira linha com pelo menos 2 células não vazias e que não contenha apenas rótulos genéricos como "Data emissão"
        $headerRowIndex = null;
        foreach ($rows as $idx => $candidate) {
            if (!is_array($candidate)) continue;
            $values = array_map(static fn($v) => trim((string) $v), $candidate);
            $nonEmpty = array_filter($values, static fn($v) => $v !== '');
            if (count($nonEmpty) < 2) continue;
            $joined = mb_strtolower(implode(' ', $values));
            if (preg_match('/^data\s*emi[sç][aã]o/i', $values['A'] ?? '')) {
                // Ignora linhas de metadados do topo
                continue;
            }
            // Aceita esta como linha de cabeçalho
            $headerRowIndex = $idx;
            break;
        }

        if ($headerRowIndex === null) {
            $this->error('Não foi possível identificar a linha de cabeçalho.');
            return self::FAILURE;
        }

        // Separa cabeçalho e dados
        $rawHeaders = $rows[$headerRowIndex] ?? [];
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        // Normaliza cabeçalhos: trim, substitui vazios por "Coluna_{A}" e garante unicidade
        $normalizedHeaders = [];
        $used = [];
        foreach ($rawHeaders as $colKey => $headerLabel) {
            $base = trim((string) $headerLabel);
            if ($base === '') {
                $base = "Coluna_{$colKey}";
            }
            $unique = $base;
            $suffix = 1;
            while (in_array($unique, $used, true)) {
                $unique = $base . '_' . $suffix;
                $suffix++;
            }
            $normalizedHeaders[$colKey] = $unique;
            $used[] = $unique;
        }

        $inserted = 0;
        DB::beginTransaction();
        try {
            foreach ($dataRows as $row) {
                $assoc = [];
                foreach ($normalizedHeaders as $colKey => $headerLabel) {
                    $value = $row[$colKey] ?? null;
                    $assoc[$headerLabel] = is_string($value) ? trim($value) : $value;
                }

                $searchText = strtolower(implode(' ', array_map(static fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $assoc)));
                $rowHash = hash('sha256', json_encode($assoc));

                $existing = Report::where('row_hash', $rowHash)->first();
                if ($existing) {
                    $existing->update([
                        'data' => $assoc,
                        'search_text' => $searchText,
                    ]);
                } else {
                    Report::create([
                        'data' => $assoc,
                        'search_text' => $searchText,
                        'row_hash' => $rowHash,
                    ]);
                    $inserted++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Erro ao importar: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Importação concluída. Novos registros: {$inserted}");
        return self::SUCCESS;
    }
} 