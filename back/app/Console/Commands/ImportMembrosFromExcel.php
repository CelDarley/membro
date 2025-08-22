<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Membro;
use Illuminate\Support\Facades\DB;

class ImportMembrosFromExcel extends Command
{
    protected $signature = 'membros:import {path? : Caminho para o arquivo Excel} {--fresh : Limpa as tabelas antes de importar}';

    protected $description = 'Importa registros do arquivo Excel para a tabela membros';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('promo.xls');
        if (!file_exists($path)) {
            $this->error("Arquivo não encontrado: {$path}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('membro_amigos')->truncate();
            DB::table('membros')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Throwable $e) {
            $this->error('Falha ao ler o Excel: ' . $e->getMessage());
            return self::FAILURE;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        if (empty($rows)) { $this->warn('Planilha vazia.'); return self::SUCCESS; }

        // Detecta cabeçalho
        $headerRowIndex = null;
        foreach ($rows as $idx => $candidate) {
            if (!is_array($candidate)) continue;
            $values = array_map(static fn($v) => trim((string) $v), $candidate);
            $nonEmpty = array_filter($values, static fn($v) => $v !== '');
            if (count($nonEmpty) >= 2) { $headerRowIndex = $idx; break; }
        }
        if ($headerRowIndex === null) { $this->error('Cabeçalho não encontrado.'); return self::FAILURE; }

        $rawHeaders = $rows[$headerRowIndex] ?? [];
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        // Mapeamento simples: tenta casar nomes de colunas usuais
        $map = $this->buildHeaderMap($rawHeaders);
        $inserted = 0; $updated = 0;

        foreach ($dataRows as $row) {
            $nome = trim((string)($row[$map['nome']] ?? ''));
            if ($nome === '') continue;
            $payload = [
                'nome' => $nome,
                'sexo' => trim((string)($row[$map['sexo']] ?? '')) ?: null,
                'concurso' => trim((string)($row[$map['concurso']] ?? '')) ?: null,
                'cargo_efetivo' => trim((string)($row[$map['cargo_efetivo']] ?? '')) ?: null,
                'titularidade' => trim((string)($row[$map['titularidade']] ?? '')) ?: null,
                'email_pessoal' => trim((string)($row[$map['email_pessoal']] ?? '')) ?: null,
                'cargo_especial' => trim((string)($row[$map['cargo_especial']] ?? '')) ?: null,
                'telefone_unidade' => trim((string)($row[$map['telefone_unidade']] ?? '')) ?: null,
                'telefone_celular' => trim((string)($row[$map['telefone_celular']] ?? '')) ?: null,
                'unidade_lotacao' => trim((string)($row[$map['unidade_lotacao']] ?? '')) ?: null,
                'comarca_lotacao' => trim((string)($row[$map['comarca_lotacao']] ?? '')) ?: null,
                'time_extraprofissionais' => trim((string)($row[$map['time_extraprofissionais']] ?? '')) ?: null,
                'quantidade_filhos' => ($row[$map['quantidade_filhos']] ?? null) !== null ? (int)$row[$map['quantidade_filhos']] : null,
                'nomes_filhos' => trim((string)($row[$map['nomes_filhos']] ?? '')) ?: null,
                'estado_origem' => substr(trim((string)($row[$map['estado_origem']] ?? '')),0,2) ?: null,
                'academico' => trim((string)($row[$map['academico']] ?? '')) ?: null,
                'pretensao_carreira' => trim((string)($row[$map['pretensao_carreira']] ?? '')) ?: null,
                'carreira_anterior' => trim((string)($row[$map['carreira_anterior']] ?? '')) ?: null,
                'lideranca' => trim((string)($row[$map['lideranca']] ?? '')) ?: null,
                'grupos_identitarios' => trim((string)($row[$map['grupos_identitarios']] ?? '')) ?: null,
            ];
            $existing = Membro::where('nome', $payload['nome'])->where('email_pessoal', $payload['email_pessoal'])->first();
            if ($existing) { $existing->update($payload); $updated++; }
            else { Membro::create($payload); $inserted++; }
        }

        $this->info("Importação concluída. Inseridos: {$inserted}, Atualizados: {$updated}");
        return self::SUCCESS;
    }

    private function buildHeaderMap(array $raw): array
    {
        // aceita variações: 'Membro' ou 'Nome'
        $aliases = [
            'nome' => ['Membro','Nome','NOME'],
            'sexo' => ['Sexo'],
            'concurso' => ['Concurso'],
            'cargo_efetivo' => ['Cargo efetivo','Cargo Efetivo'],
            'titularidade' => ['Titularidade'],
            'email_pessoal' => ['eMail pessoal','Email','E-mail','e-mail pessoal'],
            'cargo_especial' => ['Cargo Especial'],
            'telefone_unidade' => ['Telefone Unidade'],
            'telefone_celular' => ['Telefone celular'],
            'unidade_lotacao' => ['Unidade Lotação'],
            'comarca_lotacao' => ['Comarca Lotação'],
            'time_extraprofissionais' => ['Time de futebol e outros grupos extraprofissionais'],
            'quantidade_filhos' => ['Quantidade de filhos'],
            'nomes_filhos' => ['Nome dos filhos'],
            'estado_origem' => ['Estado de origem'],
            'academico' => ['Acadêmico'],
            'pretensao_carreira' => ['Pretensão de movimentação na carreira'],
            'carreira_anterior' => ['Carreira anterior'],
            'lideranca' => ['Liderança'],
            'grupos_identitarios' => ['Grupos identitários'],
        ];
        $map = [];
        foreach ($aliases as $key => $opts) {
            $map[$key] = $this->findCol($raw, $opts);
        }
        return $map;
    }

    private function findCol(array $raw, array $opts): ?string
    {
        foreach ($raw as $col => $label) {
            $t = trim((string)$label);
            foreach ($opts as $alias) {
                if (strcasecmp($t, $alias) === 0) return $col;
            }
        }
        return null; // pode ficar null e o valor vira null no payload
    }
} 