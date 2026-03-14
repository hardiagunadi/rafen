<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'name'         => ['required', 'string', 'max:150'],
            'email'        => ['required', 'email', 'max:191', 'unique:users,email'],
            'password'     => array_filter(['required', 'string', 'min:8', $this->routeIs('register') ? 'confirmed' : null]),
            'phone'        => ['sometimes', 'nullable', 'string', 'max:20'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role'         => ['sometimes', 'required', 'string', 'in:administrator,it_support,noc,keuangan,mitra,teknisi,cs'],
            'nickname'     => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Nama lengkap wajib diisi.',
            'name.max'       => 'Nama tidak boleh lebih dari 150 karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email ini sudah terdaftar sebagai akun. Silakan login atau gunakan email lain.',
            'email.max'      => 'Email tidak boleh lebih dari 191 karakter.',
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.in'        => 'Role yang dipilih tidak valid.',
        ];
    }
}
