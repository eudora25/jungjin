<?php

namespace App\Http\Requests;

use App\Models\CompanySalesAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanySalesAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CompanySalesAssignment::class) ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id ?? $this->route('company');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'cso')->where('is_active', true)),
                Rule::unique('company_sales_assignments', 'user_id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => '활성 상태의 영업사원만 담당으로 지정할 수 있습니다.',
            'user_id.unique' => '이미 이 거래처의 담당으로 지정된 영업사원입니다.',
        ];
    }
}
