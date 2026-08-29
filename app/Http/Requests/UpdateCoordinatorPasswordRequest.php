<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateCoordinatorPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTc() ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'current_password:web',
            ],
            'password' => [
                'required',
                'string',
                Password::min(8),
                'confirmed',
                'different:current_password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The current password you entered is incorrect.',
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.different' => 'The new password must be different from your current password.',
        ];
    }
}
