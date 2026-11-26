<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\SponteData as D;

class GetStudentCommand extends SponteSyncCommand
{
    protected $signature = 'student';

    protected $description = 'Sincroniza o cadastro (GetAlunos) de todos os alunos da Sponte';

    /** Não chama a Sponte por aluno: os dados já vêm no GetAlunos. */
    protected function syncStudent(array $aluno, int $studentId): void
    {
        $student = Student::updateOrCreate(
            ['student_id' => $studentId],
            [
                // A Sponte usa 0/-1 para "sem responsável"; a coluna é unsigned.
                'responsible_financial_id' => D::id($aluno['ResponsavelFinanceiroID'] ?? null),
                'responsible_didactic_id' => D::id($aluno['ResponsavelDidaticoID'] ?? null),

                'name' => D::str($aluno['Nome'] ?? null),
                'cpf' => D::str($aluno['CPF'] ?? null),
                'rg' => D::str($aluno['RG'] ?? null),
                'midia' => D::str($aluno['Midia'] ?? null),
                'date_of_birth' => D::date($aluno['DataNascimento'] ?? null),

                'email' => D::str($aluno['Email'] ?? null),
                'date_of_register' => D::date($aluno['DataCadastro'] ?? null),
                'ra' => D::str($aluno['RA'] ?? null),
                'portal_login' => D::str($aluno['LoginPortal'] ?? null),
                'portal_password' => D::str($aluno['SenhaPortal'] ?? null),
                'obs' => D::str($aluno['obs'] ?? null),
                'phone' => D::str($aluno['Telefone'] ?? null),
                'mobile_phone' => D::str($aluno['Celular'] ?? null),
                'current_class' => D::str($aluno['TurmaAtual'] ?? null),
                'enrollments_number' => D::str($aluno['NumeroMatricula'] ?? null),
                'gender' => D::str($aluno['Sexo'] ?? null),
                'status' => D::str($aluno['Situacao'] ?? null),

                'postal_code' => D::str($aluno['CEP'] ?? null),
                'address' => D::str($aluno['Endereco'] ?? null),
                'address_number' => D::str($aluno['NumeroEndereco'] ?? null),
                'city' => D::str($aluno['Cidade'] ?? null),
                'neighborhood' => D::str($aluno['Bairro'] ?? null),
                'hometown' => D::str($aluno['CidadeNatal'] ?? null),
                'defaulter' => D::str($aluno['Inadimplente'] ?? null),
                'origin' => D::str($aluno['Origem'] ?? null),
                'origin_name' => D::str($aluno['NomeOrigem'] ?? null),
                'course_interest' => D::str($aluno['CursoInteresse'] ?? null),
            ]
        );

        $this->summary->saved($student);
    }
}
