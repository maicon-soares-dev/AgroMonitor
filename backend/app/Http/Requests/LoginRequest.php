<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:50'],
            'senha' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => 'login', 'senha' => 'senha'];
    }
}
