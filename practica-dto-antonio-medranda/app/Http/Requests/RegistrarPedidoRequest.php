<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Contrato de SOLICITUD de "Registrar un pedido".
 *
 * Laravel deserializa el cuerpo JSON y aplica estas reglas antes de llegar al
 * controlador. Si alguna falla, responde 422 con el detalle por campo y el
 * controlador nunca se ejecuta (no se guarda nada).
 */
class RegistrarPedidoRequest extends FormRequest
{
    public const METODOS_PAGO = ['tarjeta', 'efectivo', 'transferencia'];

    public function authorize(): bool
    {
        // En el proyecto real lo valida el token JWT; esta práctica se centra en el contrato.
        return true;
    }

    public function rules(): array
    {
        return [
            // integer:strict exige un número JSON (25), no un texto ("25").
            'usuario_id' => ['required', 'integer:strict', 'exists:users,id'],
            'metodo_pago' => ['required', 'string', 'in:'.implode(',', self::METODOS_PAGO)],
            'direccion_entrega' => ['required', 'string', 'min:5', 'max:200'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.producto_id' => ['required', 'integer:strict', 'distinct', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer:strict', 'min:1', 'max:50'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser un texto.',
            'integer' => 'El campo :attribute debe ser un número entero (sin comillas).',
            'array' => 'El campo :attribute debe ser una lista.',
            'in' => 'El campo :attribute debe ser uno de: :values.',
            'exists' => 'El :attribute indicado no existe.',
            'distinct' => 'El :attribute está repetido en el pedido.',
            'items.min' => 'El pedido debe tener al menos un producto.',
            'items.max' => 'El pedido no puede tener más de :max productos.',
            'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'min.numeric' => 'El campo :attribute debe ser al menos :min.',
            'max.numeric' => 'El campo :attribute no puede ser mayor que :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'usuario_id' => 'usuario_id',
            'metodo_pago' => 'metodo_pago',
            'direccion_entrega' => 'direccion_entrega',
            'items.*.producto_id' => 'producto_id',
            'items.*.cantidad' => 'cantidad',
        ];
    }
}
