<?php

namespace App\Mensajeria\RabbitMq;

use App\Mensajeria\Excepciones\BrokerNoDisponible;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Conexión a RabbitMQ y declaración de la topología.
 *
 *   productor ──► exchange "tienda.notificaciones" (direct)
 *                   └─ routing key "pedido.notificacion" ──► cola "notificaciones.pedidos" ──► consumidor
 *                                                              │ nack sin reencolar
 *                                                              ▼
 *                 exchange "tienda.notificaciones.dlx" ──► cola "notificaciones.pedidos.fallidas"
 */
final class ConexionRabbitMq
{
    /**
     * @param  string  $nombre  aparece en el panel de RabbitMQ (pestaña Connections)
     *
     * @throws BrokerNoDisponible
     */
    public static function abrir(string $nombre): AbstractConnection
    {
        $c = config('mensajeria.rabbitmq');

        if (blank($c['usuario']) || blank($c['clave'])) {
            throw new BrokerNoDisponible('Faltan RABBITMQ_USER o RABBITMQ_PASSWORD en el archivo .env.');
        }

        $config = new AMQPConnectionConfig;
        $config->setHost($c['host']);
        $config->setPort($c['port']);
        $config->setVhost($c['vhost']);
        $config->setUser($c['usuario']);
        $config->setPassword($c['clave']);
        $config->setConnectionTimeout($c['timeout']);
        $config->setReadTimeout($c['timeout']);
        $config->setWriteTimeout($c['timeout']);
        $config->setChannelRPCTimeout($c['timeout']);
        $config->setConnectionName($nombre);

        try {
            return AMQPConnectionFactory::create($config);
        } catch (Throwable $e) {
            // El mensaje de la librería no incluye la clave; se agrega host y puerto para diagnosticar.
            throw new BrokerNoDisponible(
                "No se pudo conectar con RabbitMQ en {$c['host']}:{$c['port']} (vhost \"{$c['vhost']}\"): {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Crea exchange, colas y enlaces si no existen. Es idempotente: el productor,
     * el consumidor y "php artisan mensajeria:preparar" pueden llamarla siempre.
     */
    public static function declararTopologia(AMQPChannel $canal): void
    {
        $m = config('mensajeria');

        // 1) Destino de los mensajes rechazados (dead-letter). Durable: sobrevive a reinicios.
        $canal->exchange_declare($m['exchange_fallidas'], AMQPExchangeType::DIRECT, false, true, false);
        $canal->queue_declare($m['cola_fallidas'], false, true, false, false);
        $canal->queue_bind($m['cola_fallidas'], $m['exchange_fallidas'], $m['routing_key']);

        // 2) Exchange y cola principal. Si el consumidor hace nack sin reencolar,
        //    RabbitMQ mueve el mensaje al exchange de fallidas (x-dead-letter-exchange).
        $canal->exchange_declare($m['exchange'], AMQPExchangeType::DIRECT, false, true, false);
        $canal->queue_declare($m['cola'], false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $m['exchange_fallidas'],
        ]));
        $canal->queue_bind($m['cola'], $m['exchange'], $m['routing_key']);
    }
}
