<?php

namespace App\Http\Controllers\api;

use App\Exceptions\SponteException;
use App\Http\Controllers\Controller;
use App\Services\SponteService;
use Illuminate\Support\Facades\Log;

class SponteApiController extends Controller
{
    protected $sponteService;

    public function __construct(SponteService $sponteService)
    {
        $this->sponteService = $sponteService;
    }

    public function getFinanceiro()
    {
        // "AlunoID" => "11098"
        // AlunoID Devendo => 16364

        $student = $this->sponteService->getFinanceiro('16364');

        dd($student);
    }

    public function getAlunos()
    {
        try {
            return response()->json($this->sponteService->getAlunos());
        } catch (SponteException $e) {
            return $this->sponteError($e);
        }
    }

    public function getSituacoesAlunos()
    {
        $situacoes = $this->sponteService->getSituacoesAlunos('11098');

        dd($situacoes);
    }

    public function getContasPagar()
    {
        try {
            return response()->json($this->sponteService->getContasPagar());
        } catch (SponteException $e) {
            return $this->sponteError($e);
        }
    }

    /** A Sponte falhou: 502 com mensagem genérica; o detalhe (sem token) vai para o log. */
    protected function sponteError(SponteException $e)
    {
        Log::channel('sponte')->error('[api] '.$e->getMessage());

        return response()->json(['error' => 'Falha ao consultar a Sponte'], 502);
    }

    public function getMatriculas()
    {
        $enrollment = $this->sponteService->getMatriculas('16364');

        dd($enrollment);
    }
}
