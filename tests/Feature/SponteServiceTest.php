<?php

use App\Exceptions\SponteException;
use App\Services\SponteService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.sponte.url' => 'https://sponte.test/WSAPIEdu.asmx',
        'services.sponte.token' => 'TOKEN-SECRETO',
        'services.sponte.codigo_cliente' => '123',
        'services.sponte.http.retry_delay_ms' => 0,
    ]);
});

function sponteXml(string $root, string $items): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        ."<$root xmlns=\"http://api.sponteeducacional.net.br/\">$items</$root>";
}

it('normaliza um item só para uma lista', function () {
    Http::fake(['*/GetParcelas*' => Http::response(sponteXml('ArrayOfWsParcela',
        '<wsParcela><ContaReceberID>10</ContaReceberID><NumeroParcela>1</NumeroParcela></wsParcela>'
    ))]);

    $parcelas = (new SponteService)->getParcelas(1);

    expect($parcelas)->toHaveCount(1)
        ->and($parcelas[0]['ContaReceberID'])->toBe('10');
});

it('mantém a lista quando há vários itens', function () {
    Http::fake(['*/GetMatriculas*' => Http::response(sponteXml('ArrayOfWsMatricula',
        '<wsMatricula><ContratoID>1</ContratoID></wsMatricula><wsMatricula><ContratoID>2</ContratoID></wsMatricula>'
    ))]);

    expect((new SponteService)->getMatriculas(1))->toHaveCount(2);
});

it('devolve lista vazia quando a chave não vem na resposta', function () {
    Http::fake(['*' => Http::response(sponteXml('ArrayOfWsFinanceiro', ''))]);

    expect((new SponteService)->getFinanceiro(1))->toBe([]);
});

it('lança SponteException para XML inválido', function () {
    Http::fake(['*' => Http::response('isto não é xml <')]);

    (new SponteService)->getParcelas(1);
})->throws(SponteException::class, 'XML inválido');

it('lança SponteException sem o token quando a Sponte responde erro', function () {
    Http::fake(['*' => Http::response('Erro interno sToken=TOKEN-SECRETO', 500)]);

    try {
        (new SponteService)->getAlunos();
        $this->fail('Deveria lançar SponteException');
    } catch (SponteException $e) {
        expect($e->getMessage())->toContain('HTTP 500')->not->toContain('TOKEN-SECRETO');
    }
});

it('repete falhas transitórias e não expõe o token em erro de conexão', function () {
    $calls = 0;
    Http::fake(function ($request) use (&$calls) {
        $calls++;
        throw new ConnectionException('cURL error 28: timeout for '.$request->url());
    });

    try {
        (new SponteService)->getParcelas(1);
        $this->fail('Deveria lançar SponteException');
    } catch (SponteException $e) {
        expect($e->getMessage())->toContain('falha de conexão')->not->toContain('TOKEN-SECRETO');
    }

    expect($calls)->toBe(3); // 1 tentativa + 2 retries (padrão)
});

it('lê os alunos do GetAlunos', function () {
    Http::fake(['*/GetAlunos*' => Http::response(sponteXml('ArrayOfWsAluno',
        '<wsAluno><AlunoID>1</AlunoID><Nome>A</Nome></wsAluno><wsAluno><AlunoID>2</AlunoID><Nome>B</Nome></wsAluno>'
    ))]);

    $alunos = (new SponteService)->getAlunos();

    expect($alunos)->toHaveCount(2)->and($alunos[1]['Nome'])->toBe('B');
});
