<?php

namespace App\Mensajeria;

use App\Mensajeria\Excepciones\NotificacionRechazada;
use App\Models\NotificacionPedido;
use App\Support\Enmascarar;

/**
 * El trabajo "lento": buscar el correo del cliente y enviarle la notificación.
 *
 * Lo usan las dos versiones del proceso:
 *   - síncrona: dentro de la petición HTTP (el cliente espera);
 *   - asíncrona: dentro del consumidor (el cliente ya recibió su 202).
 */
class ProcesadorNotificacion
{
    /**
     * @throws NotificacionRechazada si no se puede notificar (error permanente)
     */
    public function procesar(NotificacionPedido $registro): void
    {
        $registro->marcarProcesando();

        $cliente = $registro->pedido->cliente;

        if (blank($cliente->correo)) {
            throw new NotificacionRechazada("El cliente del pedido {$registro->pedido_id} no tiene correo registrado.");
        }

        $this->enviarCorreo();

        $registro->marcarEnviada(Enmascarar::correo($cliente->correo));
    }

    /**
     * Proveedor de correo SIMULADO: no envía correos reales, solo tarda lo que
     * tardaría un servicio externo (NOTIF_TIEMPO_ENVIO_MS, 2000 ms por defecto).
     */
    protected function enviarCorreo(): void
    {
        $milisegundos = (int) config('mensajeria.tiempo_envio_ms');

        if ($milisegundos > 0) {
            usleep($milisegundos * 1000);
        }
    }
}
