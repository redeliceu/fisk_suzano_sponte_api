<?php

namespace App\Support;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Contadores de uma execução de command da Sponte e o resumo final no log `sponte`.
 */
class SyncSummary
{
    /** Quantas falhas individuais são registradas no log; as demais só entram na contagem. */
    protected const MAX_LOGGED_FAILURES = 20;

    public int $students = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** Alunos fora do filtro de ativos (não consultados nesta execução). */
    public int $outOfScope = 0;

    protected float $startedAt;

    public function __construct(protected string $command, protected string $mode = 'completo')
    {
        $this->startedAt = microtime(true);
    }

    /** Contabiliza um registro salvo com updateOrCreate. */
    public function saved(Model $model): void
    {
        if ($model->wasRecentlyCreated) {
            $this->created++;
        } elseif ($model->wasChanged()) {
            $this->updated++;
        } else {
            $this->unchanged++;
        }
    }

    public function skip(): void
    {
        $this->skipped++;
    }

    public function fail(int|string|null $studentId, Throwable $e): void
    {
        $this->failed++;

        if ($this->failed <= self::MAX_LOGGED_FAILURES) {
            Log::channel('sponte')->warning("[{$this->command}] falha no aluno {$studentId}: ".class_basename($e).': '.$e->getMessage());
        }
    }

    public function toArray(): array
    {
        return [
            'modo' => $this->mode,
            'alunos' => $this->students,
            'fora_do_filtro' => $this->outOfScope,
            'criados' => $this->created,
            'alterados' => $this->updated,
            'sem_mudanca' => $this->unchanged,
            'ignorados' => $this->skipped,
            'falhas' => $this->failed,
            'duracao_s' => round(microtime(true) - $this->startedAt, 1),
        ];
    }

    /** Escreve o resumo no console e no log `sponte`. */
    public function finish(Command $console): void
    {
        $data = $this->toArray();
        $line = collect($data)->map(fn ($v, $k) => "$k=$v")->implode(' ');

        $console->info("[{$this->command}] $line");

        $level = $this->failed > 0 ? 'warning' : 'info';
        Log::channel('sponte')->{$level}("[{$this->command}] concluído", $data);
    }

    /** Falha que impediu a execução inteira (ex.: GetAlunos indisponível). */
    public function abort(Command $console, Throwable $e): void
    {
        $message = "[{$this->command}] abortado: ".$e->getMessage();

        $console->error($message);
        Log::channel('sponte')->error($message, $this->toArray());
    }
}
