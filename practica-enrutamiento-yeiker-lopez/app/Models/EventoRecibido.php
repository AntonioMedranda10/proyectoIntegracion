<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Recorrido de un evento por el canal de entrada y, si se enrutó, por su consumidor.
 *
 *   decision: enrutado | no_enrutable | filtrado | error_transporte
 *   estado_consumo (solo si se publicó en una cola con consumidor): en_cola → procesado | fallido
 */
class EventoRecibido extends Model
{
    public const ENRUTADO = 'enrutado';

    public const NO_ENRUTABLE = 'no_enrutable';

    public const FILTRADO = 'filtrado';

    public const ERROR_TRANSPORTE = 'error_transporte';

    public const EN_COLA = 'en_cola';

    public const EN_REVISION = 'en_revision';

    public const PROCESADO = 'procesado';

    public const FALLIDO = 'fallido';

    protected $table = 'eventos_recibidos';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'evento_id', 'correlation_id', 'tipo', 'prioridad', 'decision', 'regla', 'destino', 'cola',
        'motivo', 'estado_consumo', 'consumidor', 'resultado', 'recibido_en', 'procesado_en',
    ];

    protected function casts(): array
    {
        return [
            'evento_id' => 'integer',
            'recibido_en' => 'datetime',
            'procesado_en' => 'datetime',
        ];
    }

    /** ¿Ya entró antes un evento con este id (enrutado o en revisión)? Lo usa el filtro F2. */
    public static function yaEnrutado(int $eventoId): ?self
    {
        return self::where('evento_id', $eventoId)
            ->whereIn('decision', [self::ENRUTADO, self::NO_ENRUTABLE])
            ->oldest('id')
            ->first();
    }

    /** Fila del evento enrutado que actualiza su consumidor. */
    public static function delConsumidor(int $eventoId, string $correlationId): ?self
    {
        return self::where('evento_id', $eventoId)
            ->where('correlation_id', $correlationId)
            ->where('decision', self::ENRUTADO)
            ->latest('id')
            ->first();
    }

    public function marcarProcesado(string $consumidor, string $resultado, CarbonInterface $cuando): void
    {
        $this->update([
            'estado_consumo' => self::PROCESADO,
            'consumidor' => $consumidor,
            'resultado' => Str::limit($resultado, 250),
            'procesado_en' => $cuando,
        ]);
    }

    public function marcarFallido(string $consumidor, string $motivo): void
    {
        $this->update([
            'estado_consumo' => self::FALLIDO,
            'consumidor' => $consumidor,
            'resultado' => Str::limit($motivo, 250),
            'procesado_en' => now(),
        ]);
    }
}
