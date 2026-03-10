<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantModuleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->isSubUser();
    }

    public function rules(): array
    {
        return [
            'module_hotspot_enabled' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'module_hotspot_enabled' => $this->boolean('module_hotspot_enabled'),
        ]);
    }
}
