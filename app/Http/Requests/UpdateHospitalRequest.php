<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBusinessNumber;
use App\Models\Hospital;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalRequest extends FormRequest
{
    use NormalizesBusinessNumber;

    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false; // MT-8: 공유 마스터 직접 쓰기는 platform 전용
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBusinessNumber();
    }

    public function rules(): array
    {
        $id = $this->route('hospital')?->id;

        return [
            'hospital_code' => ['nullable', 'string', 'max:50', Rule::unique('hospitals', 'hospital_code')->ignore($id)->whereNull('deleted_at')],
            'hospital_name' => ['required', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'string', 'max:20', Rule::unique('hospitals', 'business_registration_number')->ignore($id)->whereNull('deleted_at')],
            'hospital_type' => ['nullable', Rule::in(Hospital::TYPES)],
            'specialty' => ['nullable', 'string', 'max:100'],
            'representative_name' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'contact_person_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
