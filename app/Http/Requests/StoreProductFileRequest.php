<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product !== null
            && ($this->user()?->can('update', $product) ?? false);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx,zip',
            ],
            'file_type' => ['required', Rule::in(ProductFile::TYPES)],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => '첨부 파일',
            'file_type' => '문서 종류',
        ];
    }
}
