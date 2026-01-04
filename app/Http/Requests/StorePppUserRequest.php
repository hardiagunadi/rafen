<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePppUserRequest extends FormRequest
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
            'status_registrasi' => ['required', 'string', 'in:aktif,on_process'],
            'tipe_pembayaran' => ['required', 'string', 'in:prepaid,postpaid'],
            'status_bayar' => ['required', 'string', 'in:sudah_bayar,belum_bayar'],
            'status_akun' => ['required', 'string', 'in:enable,disable'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'ppp_profile_id' => ['required', 'integer', 'exists:ppp_profiles,id'],
            'tipe_service' => ['required', 'string', 'in:pppoe,l2tp_pptp,openvpn_sstp'],
            'tagihkan_ppn' => ['sometimes', 'boolean'],
            'prorata_otomatis' => ['sometimes', 'boolean'],
            'promo_aktif' => ['sometimes', 'boolean'],
            'durasi_promo_bulan' => ['nullable', 'integer', 'min:0'],
            'biaya_instalasi' => ['nullable', 'numeric', 'min:0'],
            'jatuh_tempo' => ['nullable', 'date'],
            'aksi_jatuh_tempo' => ['required', 'string', 'in:isolir,tetap_terhubung'],
            'tipe_ip' => ['required', 'string', 'in:dhcp,static'],
            'profile_group_id' => ['nullable', 'integer', 'exists:profile_groups,id'],
            'ip_static' => ['nullable', 'string', 'max:120'],
            'odp_pop' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['required', 'string', 'max:120'],
            'customer_name' => ['required', 'string', 'max:150'],
            'nik' => ['required', 'string', 'max:50'],
            'nomor_hp' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:191'],
            'alamat' => ['required', 'string'],
            'latitude' => ['nullable', 'string', 'max:120'],
            'longitude' => ['nullable', 'string', 'max:120'],
            'metode_login' => ['required', 'string', 'in:username_password,username_equals_password'],
            'username' => ['required', 'string', 'max:120'],
            'ppp_password' => ['nullable', 'string', 'max:120', 'required_if:metode_login,username_password'],
            'password_clientarea' => ['nullable', 'string', 'max:120'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
