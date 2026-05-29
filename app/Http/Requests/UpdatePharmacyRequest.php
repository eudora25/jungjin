<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false; // MT-8: 공유 마스터 직접 쓰기는 platform 전용
    }

    public function rules(): array
    {
        $id = $this->route('pharmacy')?->id;

        return [
            'pharmacy_code' => ['nullable', 'string', 'max:50', Rule::unique('pharmacies', 'pharmacy_code')->ignore($id)->whereNull('deleted_at')],
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'string', 'max:20', Rule::unique('pharmacies', 'business_registration_number')->ignore($id)->whereNull('deleted_at')],
            'representative_name' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string'],
            'landline_phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'contact_person_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
