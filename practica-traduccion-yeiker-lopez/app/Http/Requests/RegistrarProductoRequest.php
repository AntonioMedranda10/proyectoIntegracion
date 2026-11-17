<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contrato de SOLICITUD de "Registrar un producto".
 * Cada regla corresponde a una fila de la tabla de CONTRATO.md.
 * Si algo no cumple, Laravel responde 422 y no se ejecuta nada más.
 */
class RegistrarProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // En el proyecto real solo el administrador registra productos (token JWT).
        // Esta práctica se centra en el contrato, por eso no pide autenticación.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:120', Rule::unique('productos', 'nombre')],
            'description' => ['nullable', 'string', 'max:500'],
            // "strict": debe llegar como número JSON (35.5), no como texto ("35.5").
            'price' => ['bail', 'required', 'numeric:strict', 'decimal:0,2', 'min:0.01', 'max:99999.99'],
            'stock' => ['bail', 'required', 'integer:strict', 'min:0', 'max:100000'],
            'categoryId' => ['bail', 'nullable', 'integer:strict', Rule::exists('categorias', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre (name) es obligatorio.',
            'name.string' => 'El nombre (name) debe ser texto.',
            'name.min' => 'El nombre (name) debe tener al menos 3 caracteres.',
            'name.max' => 'El nombre (name) no puede superar los 120 caracteres.',
            'name.unique' => 'Ya existe un producto con ese nombre.',
            'description.string' => 'La descripción (description) debe ser texto o null.',
            'description.max' => 'La descripción (description) no puede superar los 500 caracteres.',
            'price.required' => 'El precio (price) es obligatorio.',
            'price.numeric' => 'El precio (price) debe ser un número JSON, no texto. Ejemplo: 35.5',
            'price.decimal' => 'El precio (price) admite como máximo 2 decimales.',
            'price.min' => 'El precio (price) debe ser mayor que 0.',
            'price.max' => 'El precio (price) no puede superar 99999.99.',
            'stock.required' => 'El stock es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero JSON, no texto. Ejemplo: 20',
            'stock.min' => 'El stock no puede ser negativo.',
            'stock.max' => 'El stock no puede superar 100000 unidades.',
            'categoryId.integer' => 'La categoría (categoryId) debe ser un número entero o null.',
            'categoryId.exists' => 'La categoría indicada no existe.',
        ];
    }
}
