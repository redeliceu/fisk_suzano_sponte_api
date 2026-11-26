<?php

namespace App\Console\Commands;

use App\Models\Installment;
use App\Support\SponteData;

class GetInstallmentCommand extends SponteSyncCommand
{
    protected $signature = 'installment';

    protected $description = 'Sincroniza as parcelas (GetParcelas) de todos os alunos da Sponte';

    /** Uma chamada GetParcelas por aluno; ela já traz as parcelas de todas as contas a receber. */
    protected function syncStudent(array $aluno, int $studentId): void
    {
        foreach ($this->sponte->getParcelas($studentId) as $parcela) {
            $accountReceiveId = SponteData::id($parcela['ContaReceberID'] ?? null);
            $installmentNumber = SponteData::int($parcela['NumeroParcela'] ?? null);

            if ($accountReceiveId === null || $installmentNumber === null || $installmentNumber < 0) {
                $this->summary->skip();

                continue;
            }

            $installment = Installment::updateOrCreate(
                [
                    'account_receive_id' => $accountReceiveId,
                    'installment_number' => $installmentNumber,
                ],
                [
                    'status' => SponteData::str($parcela['SituacaoParcela'] ?? null),
                    'cnab_status' => SponteData::str($parcela['SituacaoCNAB'] ?? null),
                    'due_date' => SponteData::date($parcela['DataPagamento'] ?? null),
                    'value' => SponteData::str($parcela['ValorParcela'] ?? null),
                    'paid_value' => SponteData::str($parcela['ValorPago'] ?? null),
                    'invoice_number' => SponteData::str($parcela['NumeroBoleto'] ?? null),
                    'billing_type' => SponteData::str($parcela['FormaCobranca'] ?? null),
                    'category' => SponteData::str($parcela['Categoria'] ?? null),
                ]
            );

            $this->summary->saved($installment);
        }
    }
}
