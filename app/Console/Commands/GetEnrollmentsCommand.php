<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Support\SponteData;

class GetEnrollmentsCommand extends SponteSyncCommand
{
    protected $signature = 'enrollments';

    protected $description = 'Sincroniza as matrículas (GetMatriculas) de todos os alunos da Sponte';

    protected function syncStudent(array $aluno, int $studentId): void
    {
        foreach ($this->sponte->getMatriculas($studentId) as $matricula) {
            $contractId = SponteData::id($matricula['ContratoID'] ?? null);

            if ($contractId === null) {
                $this->summary->skip();

                continue;
            }

            $enrollment = Enrollment::updateOrCreate(
                ['enrollments_id' => $contractId],
                [
                    'student_id' => SponteData::id($matricula['AlunoID'] ?? null) ?? $studentId,
                    'course_id' => SponteData::id($matricula['CursoID'] ?? null),
                    'class_id' => SponteData::id($matricula['TurmaID'] ?? null),
                    'student_name' => SponteData::str($matricula['Aluno'] ?? null),
                    'class_name' => SponteData::str($matricula['NomeTurma'] ?? null),
                    'course_name' => SponteData::str($matricula['NomeCurso'] ?? null),
                    'status' => SponteData::str($matricula['Situacao'] ?? null),
                    'start_date' => SponteData::date($matricula['DataInicio'] ?? null),
                    'deadline_date' => SponteData::date($matricula['DataTermino'] ?? null),
                    'enrollment_date' => SponteData::date($matricula['DataMatricula'] ?? null),
                    'end_date' => SponteData::date($matricula['DataEncerramento'] ?? null),
                    'contractor' => SponteData::str($matricula['Contratante'] ?? null),
                    'financial_released' => SponteData::str($matricula['FinanceiroLancado'] ?? null),
                    'contract_number' => SponteData::str($matricula['NumeroContrato'] ?? null),
                    'type_of_contract' => SponteData::str($matricula['TipoContratoID'] ?? null),
                    'type' => SponteData::str($matricula['Tipo'] ?? null),
                ]
            );

            $this->summary->saved($enrollment);
        }
    }
}
