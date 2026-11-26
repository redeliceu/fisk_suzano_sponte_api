<?php

namespace App\Services;

use App\Exceptions\SponteException;
use GuzzleHttp\Utils as GuzzleUtils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * Cliente da API da Sponte (WSAPIEdu.asmx, HTTP GET).
 *
 * Regras para quem chama:
 *  - os métodos de lista devolvem sempre uma lista (0, 1 ou N itens), nunca um item solto;
 *  - qualquer falha (HTTP, conexão, XML inválido) vira SponteException, sem o sToken na mensagem.
 */
class SponteService
{
    protected string $apiUrl;

    protected ?string $token;

    protected ?string $codigoCliente;

    /** Handler Guzzle único por instância: reaproveita a conexão (keep-alive) entre chamadas. */
    protected $handler = null;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('services.sponte.url'), '/');
        $this->token = config('services.sponte.token');
        $this->codigoCliente = config('services.sponte.codigo_cliente');
    }

    /**
     * Todos os alunos da unidade (~19 MB em produção). Lido em streaming com XMLReader.
     */
    public function getAlunos(): array
    {
        $response = $this->get('GetAlunos', 'Nome=', alunos: true);

        $previous = libxml_use_internal_errors(true);

        try {
            $reader = new \XMLReader;

            if (! $reader->XML($response->body())) {
                throw new SponteException('GetAlunos: resposta não é um XML válido.');
            }

            $students = [];

            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'wsAluno') {
                    $node = simplexml_load_string($reader->readOuterXml());

                    if ($node !== false) {
                        $students[] = json_decode(json_encode($node), true) ?: [];
                    }
                }
            }

            $errors = libxml_get_errors();
            $reader->close();

            if ($errors !== []) {
                throw new SponteException('GetAlunos: XML inválido ('.trim($errors[0]->message).').');
            }

            return $students;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Contas a receber (financeiro) do aluno. */
    public function getFinanceiro($studentId): array
    {
        return $this->getList('GetFinanceiro2', "AlunoID=$studentId", 'wsFinanceiro');
    }

    /** Parcelas do aluno, de todas as contas a receber. */
    public function getParcelas($studentId): array
    {
        return $this->getList('GetParcelas', "AlunoID=$studentId", 'wsParcela');
    }

    public function getParcelasPagar($studentId): array
    {
        return $this->getList('GetParcelasPagar', "AlunoID=$studentId", 'wsParcelaPagar');
    }

    /** Matrículas do aluno. */
    public function getMatriculas($studentId): array
    {
        return $this->getList('GetMatriculas', "AlunoID=$studentId", 'wsMatricula');
    }

    /** Resposta bruta do GetParcelas (usada só para inspeção). */
    public function getSituacoesAlunos($studentId): array
    {
        return $this->parseXml($this->get('GetParcelas', "AlunoID=$studentId"), 'GetParcelas');
    }

    /** Resposta bruta do GetParcelasPagar sem filtro (exposta em /api/contas/pagar). */
    public function getContasPagar(): array
    {
        return $this->parseXml($this->get('GetParcelasPagar'), 'GetParcelasPagar');
    }

    public function getLinhaDigitavelBoletos($accountReceivable, $numberInstallment)
    {
        $client = new \SoapClient("$this->apiUrl?wsdl", [
            'connection_timeout' => (int) config('services.sponte.http.connect_timeout', 10),
        ]);

        $params = [
            'nCodigoCliente' => $this->codigoCliente,
            'sToken' => $this->token,
            'nContaReceberID' => $accountReceivable,
            'nNumeroParcela' => $numberInstallment,
        ];

        $result = $client->__soapCall('GetLinhaDigitavelBoletos', [$params]);

        $xmlString = $result->GetLinhaDigitavelBoletosResult->any ?? null;

        if (! $xmlString || ! is_string($xmlString)) {
            return [
                'status' => 'error',
                'message' => 'Retorno inesperado da Sponte.',
                'linha_digitavel' => null,
            ];
        }

        // extrai só o trecho <diffgr:diffgram> ... </diffgr:diffgram>
        if (preg_match('/<diffgr:diffgram.*<\/diffgr:diffgram>/s', $xmlString, $matches)) {
            $xmlString = $matches[0];
        }

        $xml = $this->loadXml($xmlString, 'GetLinhaDigitavelBoletos');

        $retorno = $xml->DocumentElement->RetornosOperacao->RetornoOperacao ?? null;
        $linhaDigitavel = $xml->DocumentElement->RetornosOperacao->LinhaDigitavel ?? null;

        return [
            'status' => (string) $retorno,
            'linha_digitavel' => (string) $linhaDigitavel,
        ];
    }

    /**
     * Chama um método e devolve a lista de itens do elemento $key, normalizada:
     * nenhum item → [], um item → [item], vários → [item, ...].
     */
    protected function getList(string $method, ?string $search, string $key): array
    {
        $data = $this->parseXml($this->get($method, $search), $method);

        $items = $data[$key] ?? [];

        if (! is_array($items) || $items === []) {
            return [];
        }

        return array_is_list($items) ? $items : [$items];
    }

    /**
     * GET autenticado, com timeout, retry e falha tratada. Nunca devolve resposta com erro.
     */
    protected function get(string $method, ?string $search = null, bool $alunos = false): Response
    {
        $query = [
            'nCodigoCliente' => $this->codigoCliente,
            'sToken' => $this->token,
        ];

        if ($search !== null) {
            $query['sParametrosBusca'] = $search;
        }

        try {
            $response = $this->request($alunos)->get("$this->apiUrl/$method", $query);
        } catch (ConnectionException $e) {
            throw new SponteException("$method: falha de conexão com a Sponte ({$this->sanitize($e->getMessage())}).");
        } catch (RequestException $e) {
            $response = $e->response;
        } catch (Throwable $e) {
            throw new SponteException("$method: erro inesperado (".class_basename($e).": {$this->sanitize($e->getMessage())}).");
        }

        if ($response->failed()) {
            $body = mb_substr($this->sanitize(trim(strip_tags($response->body()))), 0, 300);

            throw new SponteException("$method: HTTP {$response->status()} da Sponte. $body");
        }

        return $response;
    }

    protected function request(bool $alunos): PendingRequest
    {
        $retries = max(0, (int) config($alunos ? 'services.sponte.http.alunos_retries' : 'services.sponte.http.retries'));
        $delay = max(0, (int) config('services.sponte.http.retry_delay_ms', 1000));

        $request = Http::connectTimeout((int) config('services.sponte.http.connect_timeout', 10))
            ->timeout((int) config($alunos ? 'services.sponte.http.alunos_timeout' : 'services.sponte.http.timeout', 60))
            ->withHeaders(['Connection' => 'keep-alive'])
            ->setHandler($this->handler ??= GuzzleUtils::chooseHandler());

        if ($retries > 0) {
            // Backoff exponencial: delay, 2×delay, 4×delay...
            $delays = array_map(fn (int $i) => $delay * (2 ** $i), range(0, $retries - 1));

            $request->retry($delays, when: fn (Throwable $e) => $this->shouldRetry($e), throw: false);
        }

        return $request;
    }

    /** Repete só falhas transitórias: conexão/timeout, 429 e 5xx. */
    protected function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }

    protected function parseXml(Response $response, string $method): array
    {
        $xml = $this->loadXml($response->body(), $method);

        return json_decode(json_encode($xml), true) ?: [];
    }

    protected function loadXml(string $body, string $method): SimpleXMLElement
    {
        if (trim($body) === '') {
            throw new SponteException("$method: resposta vazia da Sponte.");
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                $error = libxml_get_errors()[0] ?? null;

                throw new SponteException("$method: XML inválido".($error ? ' ('.trim($error->message).')' : '').'.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Remove o sToken (e o código do cliente) de qualquer texto que vá para exceção ou log. */
    protected function sanitize(string $text): string
    {
        $text = preg_replace('/(sToken=)[^&\s"\']*/i', '$1***', $text) ?? $text;

        if (filled($this->token)) {
            $text = str_replace([$this->token, rawurlencode($this->token), urlencode($this->token)], '***', $text);
        }

        return $text;
    }
}
