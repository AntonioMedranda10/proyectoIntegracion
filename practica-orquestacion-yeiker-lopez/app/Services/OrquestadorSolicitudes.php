<?php

namespace App\Services;

use App\Exceptions\DependenciaNoDisponible;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * ORQUESTADOR: coordina las llamadas a los servicios de usuarios y de estados y
 * COMPONE una sola respuesta. Los servicios no se conocen entre sí: solo el
 * orquestador sabe a quién consultar, cuánto esperar y qué hacer si uno falla.
 */
class OrquestadorSolicitudes
{
    /** Usuario fijo del laboratorio (la guía usa /usuarios/7); en un sistema real saldría de la solicitud. */
    private const USUARIO_DE_LA_SOLICITUD = 7;

    /** @var array<string, int> milisegundos que tardó cada servicio, para el registro */
    private array $tiempos = [];

    public function resumen(int $solicitudId, string $correlationId): array
    {
        $this->tiempos = [];

        // 1. ¿Quién es el usuario?
        $usuario = $this->consultar('usuarios', '/usuarios/'.self::USUARIO_DE_LA_SOLICITUD, $correlationId,
            fn (array $datos) => ($datos['id'] ?? null) === self::USUARIO_DE_LA_SOLICITUD && is_string($datos['nombre'] ?? null));

        // 2. ¿En qué estado está la solicitud? (solo si el paso 1 salió bien)
        $estado = $this->consultar('estados', "/estados/{$solicitudId}", $correlationId,
            fn (array $datos) => ($datos['solicitud_id'] ?? null) === $solicitudId && is_string($datos['estado'] ?? null));

        // 3. COMPOSICIÓN: una sola respuesta con los datos de ambos servicios.
        return [
            'solicitud_id' => $solicitudId,
            'usuario' => ['id' => $usuario['id'], 'nombre' => $usuario['nombre']],
            'estado' => $estado['estado'],
            'correlation_id' => $correlationId,
        ];
    }

    /** @return array<string, int> */
    public function tiempos(): array
    {
        return $this->tiempos;
    }

    /**
     * Llama a un servicio y devuelve su JSON solo si la respuesta es completamente
     * válida; ante cualquier problema lanza DependenciaNoDisponible con el motivo.
     */
    private function consultar(string $servicio, string $ruta, string $correlationId, callable $contenidoValido): array
    {
        $url = rtrim((string) config("orquestacion.servicios.{$servicio}"), '/').$ruta;
        $segundos = (int) config('orquestacion.timeout');
        $inicio = hrtime(true);

        try {
            // Llamada HTTP real: espera limitada y el mismo correlation_id que recibió el orquestador.
            $respuesta = Http::timeout($segundos)
                ->connectTimeout($segundos)
                ->acceptJson()
                ->withHeaders(['X-Correlation-ID' => $correlationId])
                ->get($url);
        } catch (ConnectionException $error) {
            $motivo = str_contains($error->getMessage(), 'cURL error 28')
                ? "sin respuesta en {$segundos} s (timeout)"
                : 'sin conexión';

            throw new DependenciaNoDisponible($servicio, $motivo, $url, $this->tiempos[$servicio] = $this->milisegundos($inicio));
        }

        $this->tiempos[$servicio] = $this->milisegundos($inicio);

        // successful() acepta 200–299; un 404 o un 500 cuentan como falla.
        if (! $respuesta->successful()) {
            throw new DependenciaNoDisponible($servicio, "respondió HTTP {$respuesta->status()}", $url, $this->tiempos[$servicio]);
        }

        // Un HTTP 200 no garantiza que el contenido tenga el formato esperado.
        $datos = $respuesta->json();
        if (! is_array($datos) || ! $contenidoValido($datos)) {
            throw new DependenciaNoDisponible($servicio, 'contenido inválido', $url, $this->tiempos[$servicio]);
        }

        return $datos;
    }

    private function milisegundos(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
