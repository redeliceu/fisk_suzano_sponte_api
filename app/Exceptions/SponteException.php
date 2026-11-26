<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha ao consultar a API da Sponte (HTTP, conexão ou XML inválido).
 *
 * A mensagem nunca carrega o sToken: o SponteService a higieniza antes de lançar.
 */
class SponteException extends RuntimeException {}
