<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMikrotikConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'host' => ['required', 'string', 'max:191'],
            'api_port' => ['required', 'integer', 'between:1,65535'],
            'api_ssl_port' => ['required', 'integer', 'between:1,65535'],
            'use_ssl' => ['sometimes', 'boolean'],
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:191'],
            'radius_secret' => ['nullable', 'string', 'max:191'],
            'ros_version' => ['required', 'string', 'in:6,7,auto'],
            'api_timeout' => ['required', 'integer', 'between:1,120'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'auth_port' => ['required', 'integer', 'between:1,65535'],
            'acct_port' => ['required', 'integer', 'between:1,65535'],
            'timezone' => ['required', 'string', 'max:120'],
            'isolir_url' => ['nullable', 'string', 'max:255'],
        ];
    }
}
