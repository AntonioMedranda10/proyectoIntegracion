<?php

namespace App\Mensajeria;

use App\Mensajeria\Excepciones\MensajeInvalido;
use App\Models\NotificacionPedido;
use App\Models\Pedido;
use App\Support\Fecha;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;

/**
 * Contrato del mensaje que viaja por la cola.
 *
 * {
 *   "message_id":     identifica ESTE mensaje (único; sirve para detectar duplicados),
 *   "correlation_id": identifica la OPERACIÓN completa; relaciona la petición HTTP,
 *                     el mensaje, el consumidor, los logs y la tabla de estados,
 *   "tipo":           tipo de evento ("notificacion_pedido"),
 *   "ocurrido_en":    cuándo se recibió la solicitud (UTC, ISO 8601),
 *   "payload":        datos mínimos: ids del pedido y del cliente, evento y canal.
 * }
 *
 * No lleva el correo ni el nombre del cliente: el consumidor los busca al procesar.
 */
final readonly class MensajeNotificacion
{
    public const TIPO = 'notificacion_pedido';

    public function __construct(
        public string $messageId,
        public string $correlationId,
        public string $tipo,
        public string $ocurridoEn,
        public array $payload,
    ) {}

    /** Lo usa el productor: arma el mensaje a partir del pedido validado. */
    public static function paraPedido(Pedido $pedido, string $evento, string $correlationId, CarbonInterface $ocurridoEn): self
    {
        return new self(
            messageId: (string) Str::uuid(),
            correlationId: $correlationId,
            tipo: self::TIPO,
            ocurridoEn: Fecha::iso($ocurridoEn),
            payload: [
                'pedido_id' => $pedido->id,
                'cliente_id' => $pedido->cliente_id,
                'evento' => $evento,
                'canal' => 'correo',
            ],
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
            'message_id' => ['required', 'uuid'],
            'correlation_id' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}$/D'],
            'tipo' => ['required', Rule::in([self::TIPO])],
            'ocurrido_en' => ['required', 'date'],
            'payload' => ['required', 'array'],
            'payload.pedido_id' => ['required', 'integer:strict', 'min:1'],
            'payload.cliente_id' => ['required', 'integer:strict', 'min:1'],
            'payload.evento' => ['required', Rule::in(NotificacionPedido::EVENTOS)],
            'payload.canal' => ['required', Rule::in(['correo'])],
        ]);

        if ($validador->fails()) {
            throw new MensajeInvalido(
                'El mensaje no cumple el contrato: '.implode(' ', $validador->errors()->keys()),
                is_string($datos['correlation_id'] ?? null) ? $datos['correlation_id'] : null,
            );
        }

        return new self(
            messageId: $datos['message_id'],
            correlationId: $datos['correlation_id'],
            tipo: $datos['tipo'],
            ocurridoEn: $datos['ocurrido_en'],
            payload: $datos['payload'],
        );
    }

    public function aArreglo(): array
    {
        return [
            'message_id' => $this->messageId,
            'correlation_id' => $this->correlationId,
            'tipo' => $this->tipo,
            'ocurrido_en' => $this->ocurridoEn,
            'payload' => $this->payload,
        ];
    }

    public function aJson(): string
    {
        return json_encode($this->aArreglo(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function pedidoId(): int
    {
        return $this->payload['pedido_id'];
    }

    public function evento(): string
    {
        return $this->payload['evento'];
    }
}
