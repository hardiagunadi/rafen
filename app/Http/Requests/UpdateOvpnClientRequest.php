<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOvpnClientRequest extends FormRequest
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
        $clientId = $this->route('ovpnClient')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'common_name' => ['required', 'string', 'max:150', 'unique:ovpn_clients,common_name,'.$clientId],
            'vpn_ip' => ['nullable', 'ip', 'unique:ovpn_clients,vpn_ip,'.$clientId],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama client wajib diisi.',
            'common_name.required' => 'Common Name wajib diisi.',
            'common_name.unique' => 'Common Name sudah digunakan.',
            'vpn_ip.unique' => 'IP VPN sudah digunakan.',
            'vpn_ip.ip' => 'IP VPN tidak valid.',
        ];
    }
}
