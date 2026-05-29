<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('notice')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'is_pinned' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,hwp,txt,csv,zip,jpg,jpeg,png,gif'],
            'removed_file_ids' => ['nullable', 'array'],
            'removed_file_ids.*' => ['integer', 'exists:notice_files,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => '제목',
            'content' => '본문',
            'is_pinned' => '상단 고정',
            'attachments' => '첨부파일',
            'attachments.*' => '첨부파일',
        ];
    }
}
