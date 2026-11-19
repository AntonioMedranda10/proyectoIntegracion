<?php

namespace App\Models;

use App\Mensajeria\MensajeNotificacion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Tabla de estados de cada notificación (una fila por message_id).
 *
 *   asíncrono: en_cola ──► procesando ──► enviada | fallida
 *   síncrono:  recibida ─► procesando ──► enviada | fallida
 *   si el broker no confirma: no_publicada
 */
class NotificacionPedido extends Model
{
    public const EVENTOS = ['pedido_confirmado', 'pedido_enviado', 'pedido_entregado'];

    public const MODO_ASINCRONO = 'asincrono';

    public const MODO_SINCRONO = 'sincrono';

    public const RECIBIDA = 'recibida';

    public const EN_COLA = 'en_cola';

    public const PROCESANDO = 'procesando';

    public const ENVIADA = 'enviada';

    public const FALLIDA = 'fallida';

    public const NO_PUBLICADA = 'no_publicada';

    protected $table = 'notificaciones_pedido';

    // Guarda milisegundos: hacen falta para comparar tiempos síncrono vs asíncrono.
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'message_id', 'correlation_id', 'pedido_id', 'evento', 'modo', 'estado',
        'intentos', 'destinatario', 'detalle', 'recibido_en', 'procesado_en',
    ];

    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'recibido_en' => 'datetime',
            'procesado_en' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * Cada UPDATE exige el id Y el message_id: si la base se reinició mientras el
     * consumidor procesaba (y el id se reutilizó), no se modifica la fila de otro mensaje.
     */
    protected function setKeysForSaveQuery($query)
    {
        return parent::setKeysForSaveQuery($query)->where('message_id', $this->getOriginal('message_id'));
    }

    public static function registrar(MensajeNotificacion $mensaje, string $modo, CarbonInterface $recibidoEn): self
    {
        return self::create([
            'message_id' => $mensaje->messageId,
            'correlation_id' => $mensaje->correlationId,
            'pedido_id' => $mensaje->pedidoId(),
            'evento' => $mensaje->evento(),
            'modo' => $modo,
            'estado' => $modo === self::MODO_ASINCRONO ? self::EN_COLA : self::RECIBIDA,
            'recibido_en' => $recibidoEn,
        ]);
    }

    public function marcarProcesando(): void
    {
        $this->update(['estado' => self::PROCESANDO, 'intentos' => $this->intentos + 1, 'detalle' => null]);
    }

    public function marcarEnviada(string $destinatarioEnmascarado): void
    {
        $this->update(['estado' => self::ENVIADA, 'destinatario' => $destinatarioEnmascarado, 'procesado_en' => now()]);
    }

    public function marcarFallida(string $motivo): void
    {
        $this->update(['estado' => self::FALLIDA, 'detalle' => Str::limit($motivo, 250), 'procesado_en' => now()]);
    }

    /** Error transitorio: el mensaje vuelve a la cola para un segundo intento. */
    public function marcarParaReintento(string $motivo): void
    {
        $this->update(['estado' => self::EN_COLA, 'detalle' => Str::limit('Reintento pendiente: '.$motivo, 250)]);
    }

    /**
     * Solo si sigue "en_cola": si el broker sí guardó el mensaje pero la
     * confirmación se perdió, el consumidor pudo haberlo procesado ya.
     */
    public function marcarNoPublicada(string $motivo): void
    {
        self::whereKey($this->getKey())
            ->where('estado', self::EN_COLA)
            ->update(['estado' => self::NO_PUBLICADA, 'detalle' => Str::limit($motivo, 250), 'updated_at' => $this->freshTimestampString()]);

        $this->refresh();
    }

    /** Milisegundos desde que la API recibió la petición hasta que terminó el procesamiento. */
    public function duracionMs(): ?int
    {
        if ($this->procesado_en === null) {
            return null;
        }

        return (int) round($this->recibido_en->diffInMilliseconds($this->procesado_en));
    }
}
