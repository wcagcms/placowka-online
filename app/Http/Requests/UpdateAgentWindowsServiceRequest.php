<?php

namespace App\Http\Requests;

use App\Models\AgentWindowsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentWindowsServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var AgentWindowsService|null $service */
        $service = $this->route('agentWindowsService');

        return [
            'system_name' => [
                'required',
                'string',
                'max:150',
                'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('agent_windows_services', 'system_name')->ignore($service?->id),
            ],
            'label' => ['required', 'string', 'max:190'],
            'expected_status' => ['required', Rule::in(['Running'])],
            'monitoring_enabled' => ['nullable', 'boolean'],
            'alert_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'system_name.required' => 'Podaj systemową nazwę usługi Windows.',
            'system_name.regex' => 'Nazwa systemowa może zawierać litery, cyfry, kropkę, myślnik i podkreślenie.',
            'system_name.unique' => 'Usługa o tej nazwie systemowej jest już skonfigurowana.',
            'label.required' => 'Podaj nazwę widoczną w panelu.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'system_name' => trim((string) $this->input('system_name')),
            'label' => trim((string) $this->input('label')),
            'expected_status' => 'Running',
            'monitoring_enabled' => $this->boolean('monitoring_enabled'),
            'alert_enabled' => $this->boolean('alert_enabled'),
        ]);
    }
}
