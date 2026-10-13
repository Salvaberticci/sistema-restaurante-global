<?php

namespace App\Domain\Facturacion\Exceptions;

/**
 * El emisor rechazó una operación porque el estado previo no cuadra: la nota
 * apunta a un documento inexistente, o ya hay una nota contra ese documento.
 *
 * No es un problema de red, así que reintentar no lo arregla. Se escala.
 */
final class IdempotenciaVioladaException extends \RuntimeException {}
