<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompleteAgentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'uuid'],
            'session_token' => ['required', 'string', 'min:64', 'max:160'],
            'client_nonce' => ['required', 'string', 'min:32', 'max:128'],
            'machine_name' => ['required', 'string', 'max:255'],
            'architecture' => ['nullable', 'string', 'max:32'],
            'windows_version' => ['nullable', 'string', 'max:255'],
            'setup_version' => ['nullable', 'string', 'max:50'],
            'agent_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
