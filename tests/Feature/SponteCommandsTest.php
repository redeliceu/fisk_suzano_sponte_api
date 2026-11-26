<?php

use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.sponte.url' => 'https://sponte.test/WSAPIEdu.asmx',
        'services.sponte.token' => 'TOKEN-SECRETO',
        'services.sponte.codigo_cliente' => '123',
        'services.sponte.http.retry_delay_ms' => 0,
        'services.sponte.http.retries' => 0,
    ]);
});

function alunosXml(array $alunos): string
{
    $items = collect($alunos)->map(fn ($fields) => '<wsAluno>'.collect($fields)
        ->map(fn ($v, $k) => "<$k>$v</$k>")->implode('').'</wsAluno>')->implode('');

    return '<?xml version="1.0" encoding="utf-8"?><ArrayOfWsAluno xmlns="http://api.sponteeducacional.net.br/">'.$items.'</ArrayOfWsAluno>';
}

function parcelasXml(int $conta, array $numeros): string
{
    $items = collect($numeros)->map(fn ($n) => "<wsParcela><ContaReceberID>$conta</ContaReceberID><NumeroParcela>$n</NumeroParcela>"
        .'<SituacaoParcela>A Receber</SituacaoParcela><DataPagamento>10/01/2026</DataPagamento><ValorParcela>100,00</ValorParcela></wsParcela>')->implode('');

    return '<?xml version="1.0" encoding="utf-8"?><ArrayOfWsParcela xmlns="http://api.sponteeducacional.net.br/">'.$items.'</ArrayOfWsParcela>';
}

it('installment chama GetParcelas uma vez por aluno e não chama GetFinanceiro2', function () {
    Http::fake([
        '*/GetAlunos*' => Http::response(alunosXml([['AlunoID' => 1], ['AlunoID' => 2]])),
        '*/GetParcelas*' => fn (Request $r) => Http::response(
            str_contains($r->url(), 'AlunoID%3D1') ? parcelasXml(10, [1, 2]) : parcelasXml(20, [1])
        ),
    ]);

    $this->artisan('installment')->assertSuccessful();

    expect(Installment::count())->toBe(3)
        ->and(Installment::where('account_receive_id', 10)->where('installment_number', 2)->value('due_date'))->toBe('2026-01-10');

    Http::assertSentCount(3); // 1 GetAlunos + 2 GetParcelas
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'GetFinanceiro2'));
});

it('uma falha num aluno não interrompe os demais', function () {
    Http::fake([
        '*/GetAlunos*' => Http::response(alunosXml([['AlunoID' => 1], ['AlunoID' => 2]])),
        '*/GetParcelas*' => fn (Request $r) => str_contains($r->url(), 'AlunoID%3D1')
            ? Http::response('erro', 500)
            : Http::response(parcelasXml(20, [1])),
    ]);

    $this->artisan('installment')
        ->expectsOutputToContain('falhas=1')
        ->assertSuccessful();

    expect(Installment::count())->toBe(1);
});

it('falha no GetAlunos encerra o command com erro, sem o token na saída', function () {
    Http::fake(['*' => Http::response('falhou sToken=TOKEN-SECRETO', 503)]);

    $this->artisan('student')
        ->doesntExpectOutputToContain('TOKEN-SECRETO')
        ->assertFailed();
});

it('student grava responsável negativo ou zero como null', function () {
    Http::fake(['*/GetAlunos*' => Http::response(alunosXml([
        ['AlunoID' => 5, 'Nome' => 'Ana', 'ResponsavelDidaticoID' => -1, 'ResponsavelFinanceiroID' => 0, 'DataNascimento' => '02/03/2015'],
        ['AlunoID' => 6, 'Nome' => 'Bia', 'ResponsavelDidaticoID' => 77, 'ResponsavelFinanceiroID' => 88],
    ]))]);

    $this->artisan('student')->assertSuccessful();

    $ana = Student::where('student_id', 5)->first();
    expect($ana->responsible_didactic_id)->toBeNull()
        ->and($ana->responsible_financial_id)->toBeNull()
        ->and($ana->date_of_birth)->toBe('2015-03-02')
        ->and(Student::where('student_id', 6)->value('responsible_didactic_id'))->toBe(77);
});

it('enrollments trata aluno com uma única matrícula', function () {
    Http::fake([
        '*/GetAlunos*' => Http::response(alunosXml([['AlunoID' => 1]])),
        '*/GetMatriculas*' => Http::response('<?xml version="1.0"?><ArrayOfWsMatricula xmlns="http://api.sponteeducacional.net.br/">'
            .'<wsMatricula><AlunoID>1</AlunoID><ContratoID>900</ContratoID><CursoID>-1</CursoID><NomeCurso>Inglês</NomeCurso><DataInicio>01/02/2026</DataInicio></wsMatricula>'
            .'</ArrayOfWsMatricula>'),
    ]);

    $this->artisan('enrollments')->assertSuccessful();

    $enrollment = Enrollment::where('enrollments_id', 900)->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->course_id)->toBeNull()
        ->and($enrollment->course_name)->toBe('Inglês')
        ->and($enrollment->start_date)->toBe('2026-02-01');
});

it('financial grava cada conta a receber do aluno', function () {
    Http::fake([
        '*/GetAlunos*' => Http::response(alunosXml([['AlunoID' => 1]])),
        '*/GetFinanceiro2*' => Http::response('<?xml version="1.0"?><ArrayOfWsFinanceiro xmlns="http://api.sponteeducacional.net.br/">'
            .'<wsFinanceiro><ContaReceberID>31</ContaReceberID><CodigoUnidade>2</CodigoUnidade><Aluno><wsInfoAluno><AlunoID>1</AlunoID><Nome>Ana</Nome></wsInfoAluno></Aluno></wsFinanceiro>'
            .'<wsFinanceiro><ContaReceberID>32</ContaReceberID><Aluno><wsInfoAluno><AlunoID>1</AlunoID><Nome>Ana</Nome></wsInfoAluno></Aluno></wsFinanceiro>'
            .'</ArrayOfWsFinanceiro>'),
    ]);

    $this->artisan('financial')->assertSuccessful();

    expect(\App\Models\Financial::pluck('student_name', 'account_receive_id')->all())->toBe([31 => 'Ana', 32 => 'Ana']);
});
