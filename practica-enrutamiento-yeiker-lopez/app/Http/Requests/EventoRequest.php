<?php

namespace App\Http\Requests;

use App\Enrutamiento\EnrutadorPorContenido;
use App\Enrutamiento\Evento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * VALIDACIÓN (antes del filtro y del enrutador).
 *
 * Exige el sobre del mensaje (id, tipo, prioridad, datos) y, para los tipos
 * conocidos, sus campos indispensables. NO restringe el tipo a una lista:
 * un tipo desconocido es válido aquí y el enrutador lo manda a no_enrutables.
 */
class EventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autenticación se trabajó en la práctica 2.4.
    }

    public function rules(): array
    {
        $tipo = $this->input('tipo');
        $conocido = is_string($tipo) && in_array($tipo, EnrutadorPorContenido::tiposConocidos(), true);

        return [
            'id' => ['bail', 'required', 'integer:strict', 'min:1'],
            'tipo' => ['bail', 'required', 'string', 'regex:/^[a-z][a-z_]{2,39}$/D'],
            'prioridad' => ['bail', 'required', Rule::in(Evento::PRIORIDADES)],
            // Debe venir (aunque sea {} en un tipo sin campos propios) y ser un objeto.
            'datos' => ['bail', 'present', 'array'],
            'datos.modo' => ['sometimes', Rule::in(['real', 'prueba'])],

            // Campos indispensables de los tipos conocidos.
            'datos.pedido_id' => $conocido
                ? ['bail', 'required', 'integer:strict', Rule::exists('pedidos', 'id')]
                : ['sometimes'],
            'datos.monto' => $tipo === 'pago_aprobado'
                ? ['bail', 'required', 'numeric:strict', 'gt:0', 'decimal:0,2']
                : ['sometimes'],
            'datos.metodo' => ['sometimes', Rule::in(['tarjeta', 'efectivo', 'transferencia'])],
            'datos.transportista' => $tipo === 'pedido_despachado'
                ? ['bail', 'required', 'string', 'max:60']
                : ['sometimes'],
            'datos.motivo' => $tipo === 'pedido_cancelado'
                ? ['bail', 'required', 'string', 'max:200']
                : ['sometimes'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'El identificador del evento (id) es obligatorio.',
            'id.integer' => 'El id debe ser un número entero JSON, no texto. Ejemplo: 5001',
            'id.min' => 'El id debe ser mayor que 0.',
            'tipo.required' => 'El tipo de evento (tipo) es obligatorio.',
            'tipo.string' => 'El tipo debe ser texto.',
            'tipo.regex' => 'El tipo solo admite minúsculas y guion bajo (3 a 40). Ejemplo: pago_aprobado',
            'prioridad.required' => 'La prioridad es obligatoria.',
            'prioridad.in' => 'La prioridad debe ser alta, normal o baja.',
            'datos.present' => 'El objeto datos es obligatorio (puede ir vacío: {}).',
            'datos.array' => 'datos debe ser un objeto JSON.',
            'datos.modo.in' => 'datos.modo debe ser real o prueba.',
            'datos.pedido_id.required' => 'Este tipo de evento necesita datos.pedido_id.',
            'datos.pedido_id.integer' => 'datos.pedido_id debe ser un número entero JSON. Ejemplo: 1001',
            'datos.pedido_id.exists' => 'El pedido indicado en datos.pedido_id no existe.',
            'datos.monto.required' => 'Un pago_aprobado necesita datos.monto.',
            'datos.monto.numeric' => 'datos.monto debe ser un número JSON. Ejemplo: 45.90',
            'datos.monto.gt' => 'datos.monto debe ser mayor que 0.',
            'datos.monto.decimal' => 'datos.monto admite como máximo 2 decimales.',
            'datos.metodo.in' => 'datos.metodo debe ser tarjeta, efectivo o transferencia.',
            'datos.transportista.required' => 'Un pedido_despachado necesita datos.transportista.',
            'datos.motivo.required' => 'Un pedido_cancelado necesita datos.motivo.',
        ];
    }

    /** Deja constancia del rechazo por contrato antes de responder 422. */
    protected function failedValidation(Validator $validator): void
    {
        Log::channel('enrutamiento')->warning('validacion: evento rechazado por el contrato; no pasa al filtro ni al enrutador', [
            'campos' => $validator->errors()->keys(),
            'tipo' => is_string($this->input('tipo')) ? $this->input('tipo') : null,
        ]);

        parent::failedValidation($validator);
    }
}
