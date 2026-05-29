<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'string', 'max:20'],
            'representative_name' => ['nullable', 'string', 'max:100'],
            'company_group' => ['nullable', 'string', 'max:100'],
            'default_commission_grade' => ['nullable', Rule::in(['A', 'B', 'C', 'D', 'E'])],
            'postcode' => ['nullable', 'string', 'max:10'],
            'business_address' => ['nullable', 'string'],
            'contact_person_name' => ['nullable', 'string', 'max:100'],
            'landline_phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'mobile_phone_2' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'receive_email' => ['nullable', 'email', 'max:255'],
            'assigned_pharmacist_contact' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'approval_status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_name' => '업체명',
            'business_registration_number' => '사업자등록번호',
            'representative_name' => '대표자명',
            'company_group' => '업체구분',
            'default_commission_grade' => '수수료 등급',
            'postcode' => '우편번호',
            'business_address' => '사업장 주소',
            'contact_person_name' => '담당자명',
            'landline_phone' => '대표 전화',
            'mobile_phone' => '휴대폰',
            'mobile_phone_2' => '보조 휴대폰',
            'email' => '이메일',
            'receive_email' => '수신 이메일',
            'assigned_pharmacist_contact' => '담당 제약사 관리자',
            'remarks' => '비고',
            'status' => '상태',
            'approval_status' => '승인 상태',
        ];
    }
}
