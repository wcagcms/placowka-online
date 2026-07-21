<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StartAgentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:10', 'max:40'],
            'machine_name' => ['required', 'string', 'max:255'],
            'client_nonce' => ['required', 'string', 'min:32', 'max:128'],
            'architecture' => ['nullable', 'string', 'max:32'],
            'windows_version' => ['nullable', 'string', 'max:255'],
            'setup_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
