<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('company')) ?? false;
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
        return (new StoreCompanyRequest)->attributes();
    }
}
