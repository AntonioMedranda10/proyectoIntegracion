<?php

namespace App\Http\Requests;

use App\Models\NotificacionPedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * JSON que acepta la API antes de publicar nada en la cola:
 *
 *   { "pedidoId": 1001, "evento": "pedido_confirmado" }
 *
 * Si no cumple, Laravel responde 422 y el mensaje NUNCA llega al broker.
 */
class NotificacionPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autenticación se trabajó en la práctica 2.4; aquí el foco es la mensajería.
        return true;
    }

    public function rules(): array
    {
        return [
            // "strict": debe llegar como número JSON (1001), no como texto ("1001").
            'pedidoId' => ['bail', 'required', 'integer:strict', 'min:1', Rule::exists('pedidos', 'id')],
            'evento' => ['bail', 'required', 'string', Rule::in(NotificacionPedido::EVENTOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'pedidoId.required' => 'El pedido (pedidoId) es obligatorio.',
            'pedidoId.integer' => 'El pedido (pedidoId) debe ser un número entero JSON, no texto. Ejemplo: 1001',
            'pedidoId.min' => 'El pedido (pedidoId) debe ser mayor que 0.',
            'pedidoId.exists' => 'El pedido indicado no existe.',
            'evento.required' => 'El evento es obligatorio.',
            'evento.string' => 'El evento debe ser texto.',
            'evento.in' => 'El evento debe ser uno de: '.implode(', ', NotificacionPedido::EVENTOS).'.',
        ];
    }
}
