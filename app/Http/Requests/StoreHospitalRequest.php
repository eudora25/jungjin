<?php

namespace App\Http\Requests;

use App\Models\Hospital;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHospitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'hospital_code' => ['nullable', 'string', 'max:50', Rule::unique('hospitals', 'hospital_code')->whereNull('deleted_at')],
            'hospital_name' => ['required', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'string', 'max:20', Rule::unique('hospitals', 'business_registration_number')->whereNull('deleted_at')],
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

    public function attributes(): array
    {
        return [
            'hospital_code' => '병의원 코드',
            'hospital_name' => '병의원명',
            'business_registration_number' => '사업자등록번호',
            'hospital_type' => '병의원 유형',
            'specialty' => '전문 분야',
            'representative_name' => '대표자명',
            'postcode' => '우편번호',
            'address' => '주소',
            'phone' => '대표 전화',
            'contact_person_name' => '담당자명',
            'contact_phone' => '담당자 연락처',
            'email' => '이메일',
            'remarks' => '비고',
            'status' => '상태',
        ];
    }
}
