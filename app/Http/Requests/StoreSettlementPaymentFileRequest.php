<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GAP-5-4: 정산 지급 증빙 파일 업로드 요청.
 *
 * 이체확인서·지급내역 캡처 등. PDF/이미지/문서. 최대 10MB.
 */
class StoreSettlementPaymentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,hwp'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => '지급 증빙 파일',
        ];
    }
}
