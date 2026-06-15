<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('password') === null || $this->input('password') === '') {
            $this->request->remove('password');
            $this->request->remove('password_confirmation');
        }
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user instanceof User ? $user->id : (int) $user;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            // 관리 대상은 cso 로 고정(§6.9) — 역할 변경/승격 불가.
            'role' => ['required', Rule::in(['cso'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
