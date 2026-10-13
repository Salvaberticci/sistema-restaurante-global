<?php

/**
 * Escáner de código para los tests arquitectónicos de AGENTS.md.
 *
 * Trabaja sobre tokens de PHP, no sobre el texto crudo, para que un `now()` o
 * un `0.16` escritos en un comentario no cuenten como una infracción. Un test
 * que marca los comentarios castiga a quien lo está leyendo bien, y el
 * resultado es que se termina ignorando el test en vez de arreglar el código.
 */

namespace Tests\Support;

final class Codigo
{
    /**
     * Devuelve el archivo sin comentarios ni cadenas literales, listo para
     * buscar patrones de código real.
     *
     * Se conservan los tokens de whitespace para no separar identificadores
     * que en el código están juntos: insertar un espacio entre cada token
     * rompería patrones como `::now`.
     */
    public static function sinComentarios(string $contenido): string
    {
        $tokens = token_get_all($contenido);
        $limpio = '';

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $limpio .= $token;

                continue;
            }

            [$id, $texto] = $token;

            // T_COMMENT, T_DOC_COMMENT y T_CONSTANT_ENCAPSED_STRING
            if (in_array($id, [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)) {
                continue;
            }

            $limpio .= $texto;
        }

        return $limpio;
    }

    /**
     * Recorre el dominio y devuelve las infracciones por archivo.
     *
     * @param  list<string>  $prohibidos  Literales exactos.
     * @param  array<string, string>  $patrones  [descripción, regex]
     * @return list<string>
     */
    public static function infracciones(array $prohibidos, array $patrones = []): array
    {
        $infracciones = [];

        foreach (self::archivosDeDominio() as $relativo) {
            $codigo = self::sinComentarios(file_get_contents($relativo['ruta']));

            foreach ($prohibidos as $prohibido) {
                if (str_contains($codigo, $prohibido)) {
                    $infracciones[] = $relativo['nombre'].' contiene el literal "'.$prohibido.'"';
                }
            }

            foreach ($patrones as $descripcion => $regex) {
                if (preg_match($regex, $codigo, $coincidencia)) {
                    $infracciones[] = $relativo['nombre'].' '.$descripcion.': '.$coincidencia[0];
                }
            }
        }

        return $infracciones;
    }

    /**
     * Archivos PHP de `app/Domain`, sin depender de Finder.
     *
     * @return list<array{ruta: string, nombre: string}>
     */
    public static function archivosDeDominio(): array
    {
        $raiz = app_path('Domain');

        if (! is_dir($raiz)) {
            return [];
        }

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
        );

        $archivos = [];

        foreach ($iterador as $archivo) {
            if (! $archivo instanceof \SplFileInfo || $archivo->getExtension() !== 'php') {
                continue;
            }

            $archivos[] = [
                'ruta' => $archivo->getPathname(),
                'nombre' => substr($archivo->getPathname(), strlen($raiz) + 1),
            ];
        }

        usort($archivos, fn (array $a, array $b) => $a['nombre'] <=> $b['nombre']);

        return $archivos;
    }
}
