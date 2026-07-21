<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentWindowsServiceRequest;
use App\Http\Requests\UpdateAgentWindowsServiceRequest;
use App\Models\AgentWindowsService;
use App\Services\AgentWindowsServiceConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AgentWindowsServiceController extends Controller
{
    public function __construct(
        private readonly AgentWindowsServiceConfigService $configService
    ) {
    }

    public function index(): View
    {
        return view('windows-services.index', [
            'services' => AgentWindowsService::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function store(StoreAgentWindowsServiceRequest $request): RedirectResponse
    {
        AgentWindowsService::create($request->validated());
        $this->configService->forgetCache();

        return redirect()
            ->route('agent-windows-services.index')
            ->with('success', 'Usługa Windows została dodana. Nowa konfiguracja trafi do kolejnych generowanych paczek agenta.');
    }

    public function update(
        UpdateAgentWindowsServiceRequest $request,
        AgentWindowsService $agentWindowsService
    ): RedirectResponse {
        $agentWindowsService->update($request->validated());
        $this->configService->forgetCache();

        return redirect()
            ->route('agent-windows-services.index')
            ->with('success', 'Konfiguracja usługi Windows została zapisana.');
    }

    public function destroy(AgentWindowsService $agentWindowsService): RedirectResponse
    {
        $label = $agentWindowsService->label;
        $agentWindowsService->delete();
        $this->configService->forgetCache();

        return redirect()
            ->route('agent-windows-services.index')
            ->with('success', 'Usługa „'.$label.'” została usunięta z konfiguracji nowych agentów.');
    }
}
