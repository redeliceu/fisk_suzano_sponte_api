<?php

namespace App\Console\Commands;

use App\Models\Financial;
use App\Support\SponteData;

class GetStudentFinancialCommand extends SponteSyncCommand
{
    protected $signature = 'financial';

    protected $description = 'Sincroniza as contas a receber (GetFinanceiro2) de todos os alunos da Sponte';

    protected function syncStudent(array $aluno, int $studentId): void
    {
        foreach ($this->sponte->getFinanceiro($studentId) as $conta) {
            $accountReceiveId = SponteData::id($conta['ContaReceberID'] ?? null);

            if ($accountReceiveId === null) {
                $this->summary->skip();

                continue;
            }

            $infoAluno = $conta['Aluno']['wsInfoAluno'] ?? [];

            $financial = Financial::updateOrCreate(
                ['account_receive_id' => $accountReceiveId],
                [
                    'unit_code' => SponteData::id($conta['CodigoUnidade'] ?? null),
                    'student_id' => SponteData::id($infoAluno['AlunoID'] ?? null) ?? $studentId,
                    'student_name' => SponteData::str($infoAluno['Nome'] ?? null),
                    'number_of_parcels' => SponteData::str($conta['NumeroParcelas'] ?? null),
                    'contract_number' => SponteData::str($conta['NumeroContrato'] ?? null),
                    'total_gross_value' => SponteData::str($conta['TotalValorBruto'] ?? null),
                    'total_net_value' => SponteData::str($conta['TotalValorLiquido'] ?? null),
                    'total_discount_reais' => SponteData::str($conta['TotalDescontoReais'] ?? null),
                    'total_discount_percentage' => SponteData::str($conta['TotalDescontoPercentual'] ?? null),
                    'category' => SponteData::str($conta['Categoria'] ?? null),
                ]
            );

            $this->summary->saved($financial);
        }
    }
}
