<?php

namespace App\Enrutamiento;

use App\Enrutamiento\Excepciones\MensajeInvalido;
use App\Support\Fecha;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

/**
 * Contrato del mensaje (evento del ciclo de vida de un pedido):
 *
 * {
 *   "id":             identificador único del evento (lo asigna el emisor),
 *   "tipo":           pago_aprobado | pedido_despachado | pedido_cancelado | (otro → no_enrutables),
 *   "prioridad":      alta | normal | baja,
 *   "correlation_id": operación a la que pertenece (X-Correlation-ID, igual que en la práctica 3.1),
 *   "recibido_en":    cuándo lo recibió el canal de entrada (UTC),
 *   "datos":          contenido propio de cada tipo (pedido_id, monto, transportista, motivo, modo…)
 * }
 */
final readonly class Evento
{
    public const PRIORIDADES = ['alta', 'normal', 'baja'];

    public function __construct(
        public int $id,
        public string $tipo,
        public string $prioridad,
        public string $correlationId,
        public string $recibidoEn,
        public array $datos,
    ) {}

    /** Lo arma el canal de entrada con los datos ya validados por EventoRequest. */
    public static function desdeEntrada(array $validado, string $correlationId, CarbonInterface $recibidoEn): self
    {
        return new self(
            id: $validado['id'],
            tipo: $validado['tipo'],
            prioridad: $validado['prioridad'],
            correlationId: $correlationId,
            recibidoEn: Fecha::iso($recibidoEn),
            // validated() omite "datos" cuando llega vacío ({}).
            datos: $validado['datos'] ?? [],
        );
    }

    /**
     * Lo usa el consumidor: no confía en lo que llega de la cola y vuelve a
     * comprobar el contrato antes de procesar.
     *
     * @throws MensajeInvalido
     */
    public static function desdeJson(string $json): self
    {
        try {
            $datos = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MensajeInvalido('El cuerpo del mensaje no es JSON válido.');
        }
        $datos = is_array($datos) ? $datos : [];

        $validador = Validator::make($datos, [
            'id' => ['required', 'integer:strict', 'min:1'],
            'tipo' => ['required', 'string', 'regex:/^[a-z][a-z_]{2,39}$/D'],
            'prioridad' => ['required', Rule::in(self::PRIORIDADES)],
            'correlation_id' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}$/D'],
            'recibido_en' => ['required', 'date'],
            'datos' => ['present', 'array'],
        ]);

        if ($validador->fails()) {
            throw new MensajeInvalido('El mensaje no cumple el contrato: '.implode(', ', $validador->errors()->keys()));
        }

        return new self($datos['id'], $datos['tipo'], $datos['prioridad'], $datos['correlation_id'], $datos['recibido_en'], $datos['datos']);
    }

    public function dato(string $clave, mixed $defecto = null): mixed
    {
        return $this->datos[$clave] ?? $defecto;
    }

    /** El emisor marca así los eventos de su entorno de pruebas (por ejemplo, la pasarela en sandbox). */
    public function enModoPrueba(): bool
    {
        return ($this->datos['modo'] ?? 'real') === 'prueba';
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'prioridad' => $this->prioridad,
            'correlation_id' => $this->correlationId,
            'recibido_en' => $this->recibidoEn,
            'datos' => $this->datos ?: new \stdClass, // vacío se publica como {} y no como []
        ];
    }

    public function aJson(): string
    {
        return json_encode($this->aArreglo(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
