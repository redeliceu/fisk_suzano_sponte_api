<?php

namespace App\Console\Commands;

use App\Exceptions\SponteException;
use App\Services\SponteService;
use App\Support\SponteData;
use App\Support\SyncSummary;
use Illuminate\Console\Command;
use Throwable;

/**
 * Base dos commands de sincronização: baixa a lista de alunos e processa um a um,
 * isolando falhas por aluno e terminando com um resumo da execução.
 */
abstract class SponteSyncCommand extends Command
{
    protected SponteService $sponte;

    protected SyncSummary $summary;

    /** Sincroniza um aluno. Exceções lançadas aqui contam como falha só desse aluno. */
    abstract protected function syncStudent(array $aluno, int $studentId): void;

    public function handle(SponteService $sponte): int
    {
        $this->sponte = $sponte;
        $this->summary = new SyncSummary($this->getName());

        try {
            $alunos = $sponte->getAlunos();
        } catch (SponteException $e) {
            $this->summary->abort($this, $e);

            return self::FAILURE;
        }

        foreach ($alunos as $aluno) {
            $studentId = SponteData::id($aluno['AlunoID'] ?? null);

            if ($studentId === null) {
                $this->summary->skip();

                continue;
            }

            $this->summary->students++;

            try {
                $this->syncStudent($aluno, $studentId);
            } catch (Throwable $e) {
                $this->summary->fail($studentId, $e);
            }
        }

        $this->summary->finish($this);

        return self::SUCCESS;
    }
}
