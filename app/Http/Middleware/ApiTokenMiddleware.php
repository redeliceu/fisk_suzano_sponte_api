<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /*
        $token = $request->header('X-API-TOKEN'); // Token vem do header

        if ($token !== env('API_ACCESS_TOKEN')) {
            return response()->json(['error' => 'Acesso não autorizado'], 401);
        }

        return $next($request); */

         // Pega o header Authorization (Bearer xxxx)
        $authorization = $request->header('Authorization');

        // Remove o prefixo Bearer (se existir)
        if ($authorization && preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            $token = trim($matches[1]); // Só o token
        } else {
            $token = null;
        }

        // Compara com o token do .env. Token vazio/ausente no .env NUNCA libera acesso
        // (antes: sem API_ACCESS_TOKEN definido, null === null deixava passar sem token).
        $expected = (string) env('API_ACCESS_TOKEN', '');
        if ($expected === '' || $token === null || ! hash_equals($expected, (string) $token)) {
            return response()->json(['error' => 'Acesso não autorizado'], 401);
        }

        return $next($request);
    }
}
