<?php

namespace App\Enrutamiento\RabbitMq;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Conexión a RabbitMQ y topología de la práctica 3.2:
 *
 *   exchange "tienda.eventos" (direct)
 *     ├─ routing key "facturacion"   → cola tienda.facturacion   → consumidor Facturación
 *     ├─ routing key "logistica"     → cola tienda.logistica     → consumidor Logística
 *     ├─ routing key "inventario"    → cola tienda.inventario    → consumidor Inventario
 *     └─ routing key "no_enrutables" → cola tienda.no_enrutables → revisión manual
 *
 *   Fallos de procesamiento (nack sin reencolar) → exchange "tienda.eventos.dlx" → cola tienda.eventos.fallidos
 */
final class ConexionRabbitMq
{
    /** @throws BrokerNoDisponible */
    public static function abrir(string $nombre): AbstractConnection
    {
        $c = config('enrutamiento.rabbitmq');

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
            // El mensaje de la librería no incluye la clave.
            throw new BrokerNoDisponible(
                "No se pudo conectar con RabbitMQ en {$c['host']}:{$c['port']} (vhost \"{$c['vhost']}\"): {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /** Crea exchanges, colas y enlaces si no existen. Es idempotente. */
    public static function declararTopologia(AMQPChannel $canal): void
    {
        $c = config('enrutamiento');

        // Fallos de procesamiento: fanout hacia una sola cola de revisión técnica.
        $canal->exchange_declare($c['exchange_fallidos'], AMQPExchangeType::FANOUT, false, true, false);
        $canal->queue_declare($c['cola_fallidos'], false, true, false, false);
        $canal->queue_bind($c['cola_fallidos'], $c['exchange_fallidos']);

        // Exchange principal y una cola por destino, enlazada con su routing key.
        $canal->exchange_declare($c['exchange'], AMQPExchangeType::DIRECT, false, true, false);
        foreach (Destino::cases() as $destino) {
            $argumentos = ['x-max-priority' => 10];
            if ($destino !== Destino::NoEnrutables) {
                $argumentos['x-dead-letter-exchange'] = $c['exchange_fallidos'];
            }
            $canal->queue_declare($destino->cola(), false, true, false, false, false, new AMQPTable($argumentos));
            $canal->queue_bind($destino->cola(), $c['exchange'], $destino->routingKey());
        }
    }
}
