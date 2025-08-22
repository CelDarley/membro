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

        // Mapeamento robusto de colunas
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

        // Segunda passada: relacionamentos (amigos)
        DB::table('membro_amigos')->truncate();
        $this->importFriends($dataRows, $map);

        $this->info("Importação concluída. Inseridos: {$inserted}, Atualizados: {$updated}");
        return self::SUCCESS;
    }

    private function buildHeaderMap(array $raw): array
    {
        // aceita variações de nomes e normaliza acentos/caixa/espaços
        $aliases = [
            'nome' => ['Membro','Nome','NOME'],
            'sexo' => ['Sexo','SEXO'],
            'concurso' => ['Concurso'],
            'cargo_efetivo' => ['Cargo efetivo','Cargo Efetivo','CARGO EFETIVO'],
            'titularidade' => ['Titularidade'],
            'email_pessoal' => ['eMail pessoal','Email pessoal','Email','E-mail','e-mail pessoal'],
            'cargo_especial' => ['Cargo Especial','CARGO ESPECIAL'],
            'telefone_unidade' => ['Telefone Unidade','Telefone da Unidade','Telefone (Unidade)'],
            'telefone_celular' => ['Telefone celular','Celular','Telefone Celular'],
            'unidade_lotacao' => ['Unidade Lotação','Unidade de Lotação','Unidade de lotação'],
            'comarca_lotacao' => ['Comarca Lotação','Comarca de Lotação','Comarca'],
            'time_extraprofissionais' => ['Time de futebol e outros grupos extraprofissionais','Grupos extraprofissionais','Time de futebol'],
            'quantidade_filhos' => ['Quantidade de filhos','Qtde de filhos','Qtd filhos'],
            'nomes_filhos' => ['Nome dos filhos','Nomes dos filhos'],
            'estado_origem' => ['Estado de origem','UF de origem','Estado origem'],
            'academico' => ['Acadêmico','Academico'],
            'pretensao_carreira' => ['Pretensão de movimentação na carreira','Pretensao de movimentacao na carreira','Pretensão de carreira'],
            'carreira_anterior' => ['Carreira anterior'],
            'lideranca' => ['Liderança','Lideranca'],
            'grupos_identitarios' => ['Grupos identitários','Grupos identitarios'],
            // amigos: coluna opcional
            'amigos_col' => ['Amigos no MP','Amigos no MP (IDs)','Amigos no MP (Nomes)','Amigos MP'],
        ];
        $map = [];
        foreach ($aliases as $key => $opts) {
            $map[$key] = $this->findCol($raw, $opts);
        }
        return $map;
    }

    private function normalize(string $s): string
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        // remover acentos
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        // remover não alfanumérico
        $s = preg_replace('/[^a-z0-9]+/','', $s) ?? $s;
        return $s;
    }

    private function findCol(array $raw, array $opts): ?string
    {
        $normOpts = array_map(fn($v) => $this->normalize((string)$v), $opts);
        foreach ($raw as $col => $label) {
            $t = $this->normalize((string)$label);
            foreach ($normOpts as $n) {
                if ($t === $n) return $col; // match exato normalizado
                if ($n !== '' && $t !== '' && (str_contains($t, $n) || str_contains($n, $t))) return $col; // match parcial
            }
        }
        return null; // pode ficar null e o valor vira null no payload
    }

    private function importFriends(array $dataRows, array $map): void
    {
        $nomeCol = $map['nome'] ?? null;
        $amigosCol = $map['amigos_col'] ?? null;
        if (!$nomeCol || !$amigosCol) return;

        // mapa de nome normalizado -> id
        $all = Membro::select('id','nome')->get();
        $nameToId = [];
        foreach ($all as $m) {
            $key = $this->normalize((string)$m->nome);
            if ($key !== '') $nameToId[$key] = (int)$m->id;
        }

        foreach ($dataRows as $row) {
            $nome = trim((string)($row[$nomeCol] ?? ''));
            if ($nome === '') continue;
            $ownerId = Membro::where('nome',$nome)->value('id');
            if (!$ownerId) continue;

            $raw = $row[$amigosCol] ?? null;
            if ($raw === null || $raw === '') continue;
            $str = trim((string)$raw);
            if ($str === '') continue;

            $friendIds = [];
            // tentar capturar IDs
            $ids = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', $str) ?: []), fn($n)=>$n>0));
            if (!empty($ids)) {
                $friendIds = $ids;
            } else {
                // dividir por separadores e casar por nome
                $tokens = array_values(array_filter(preg_split('/[\n,;]+/', $str) ?: [], fn($t)=>trim($t) !== ''));
                foreach ($tokens as $t) {
                    $key = $this->normalize((string)$t);
                    if ($key !== '' && isset($nameToId[$key])) $friendIds[] = (int)$nameToId[$key];
                }
            }
            $friendIds = array_values(array_unique(array_filter($friendIds, fn($id)=>$id !== (int)$ownerId)));
            if (empty($friendIds)) continue;

            // anexar nas duas direções
            $owner = Membro::find($ownerId);
            if (!$owner) continue;
            $owner->amigos()->syncWithoutDetaching($friendIds);
            foreach ($friendIds as $fid) {
                $peer = Membro::find($fid);
                if ($peer) $peer->amigos()->syncWithoutDetaching([$ownerId]);
            }
        }
    }
} 