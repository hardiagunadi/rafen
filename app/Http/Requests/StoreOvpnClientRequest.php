<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvpnClientRequest extends FormRequest
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
            'mikrotik_connection_id' => ['required', 'integer', 'exists:mikrotik_connections,id', 'unique:ovpn_clients,mikrotik_connection_id'],
            'name' => ['required', 'string', 'max:150'],
            'common_name' => ['nullable', 'string', 'max:150', 'unique:ovpn_clients,common_name'],
            'username' => ['nullable', 'string', 'max:150', 'unique:ovpn_clients,username'],
            'password' => ['nullable', 'string', 'max:150', 'unique:ovpn_clients,password'],
            'vpn_ip' => ['nullable', 'ip', 'unique:ovpn_clients,vpn_ip'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mikrotik_connection_id.required' => 'Pilih router untuk client OpenVPN.',
            'mikrotik_connection_id.exists' => 'Router tidak ditemukan.',
            'mikrotik_connection_id.unique' => 'Router sudah memiliki client OpenVPN.',
            'name.required' => 'Nama client wajib diisi.',
            'common_name.unique' => 'Common Name sudah digunakan.',
            'username.unique' => 'Username sudah digunakan.',
            'password.unique' => 'Password sudah digunakan.',
            'vpn_ip.unique' => 'IP VPN sudah digunakan.',
            'vpn_ip.ip' => 'IP VPN tidak valid.',
        ];
    }
}
