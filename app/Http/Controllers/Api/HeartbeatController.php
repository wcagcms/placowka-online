<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Heartbeat;
use App\Services\AgentEnrollmentService;
use App\Services\IncidentLifecycleService;
use App\Services\IncidentNotificationService;
use App\Services\SmartDiskStatusService;
use App\Services\WindowsSecurityStatusService;
use App\Services\WindowsServiceStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class HeartbeatController extends Controller
{
    private const DIAGNOSTIC_INCIDENT_TYPES = [
        'gateway_problem',
        'dns_problem',
        'internet_problem',
        'monitoring_server_problem',
    ];

    public function __invoke(
        Request $request,
        string $uuid,
        IncidentNotificationService $notifications,
        WindowsServiceStatusService $windowsServices,
        SmartDiskStatusService $smartDisks,
        WindowsSecurityStatusService $windowsSecurity,
        IncidentLifecycleService $incidents,
        AgentEnrollmentService $enrollment
    ): JsonResponse {
        $maxBytes = max(32768, (int) config('placowka.heartbeat_max_payload_bytes', 262144));

        if (! $request->isJson()) {
            return response()->json([
                'ok' => false,
                'message' => 'Content-Type application/json is required.',
            ], 415);
        }

        if (strlen($request->getContent()) > $maxBytes) {
            return response()->json([
                'ok' => false,
                'message' => 'Heartbeat payload is too large.',
            ], 413);
        }

        $token = (string) $request->bearerToken();

        if ($token === '' || strlen($token) < 32 || strlen($token) > 128) {
            return $this->unauthorized();
        }

        $device = Device::query()
            ->with('facility')
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->first();

        if (
            ! $device
            || strlen((string) $device->token_hash) !== 64
            || ! hash_equals((string) $device->token_hash, hash('sha256', $token))
        ) {
            return $this->unauthorized();
        }

        $validated = $request->validate($this->rules());
        $receivedAt = now();
        $reportedAt = $this->reportedAt($validated['checked_at'] ?? null, $receivedAt);
        $isReplay = (bool) data_get($validated, 'delivery.is_replay', false);
        $heartbeatUuid = $validated['heartbeat_uuid'] ?? null;

        if ($reportedAt->lt($receivedAt->copy()->subDays(8))) {
            return response()->json([
                'ok' => false,
                'message' => 'Queued heartbeat is older than the accepted retention window.',
            ], 422);
        }

        if ($heartbeatUuid) {
            $duplicate = Heartbeat::query()
                ->where('device_id', $device->id)
                ->where('heartbeat_uuid', $heartbeatUuid)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Heartbeat already accepted.',
                    'duplicate' => true,
                    'heartbeat_id' => $duplicate->id,
                    'server_time' => $receivedAt->toIso8601String(),
                ]);
            }
        }

        $internetOk = (bool) $validated['internet_ok'];
        $dnsOk = (bool) $validated['dns_ok'];
        $gatewayOk = array_key_exists('gateway_ok', $validated) && $validated['gateway_ok'] !== null
            ? (bool) $validated['gateway_ok']
            : null;
        $monitoringServerOk = array_key_exists('monitoring_server_ok', $validated)
            && $validated['monitoring_server_ok'] !== null
                ? (bool) $validated['monitoring_server_ok']
                : null;

        $diagnosticStatus = $this->determineDiagnosticStatus(
            $gatewayOk,
            $dnsOk,
            $internetOk,
            $monitoringServerOk
        );

        $status = $diagnosticStatus === 'online' ? 'online' : 'problem';
        $payload = $validated;
        $payload['reported_diagnostic_status'] = $validated['diagnostic_status'] ?? null;
        $payload['diagnostic_status'] = $diagnosticStatus;
        $payload['received_at'] = $receivedAt->toIso8601String();
        $payload['is_replayed'] = $isReplay;

        $queueDelaySeconds = $isReplay
            ? max(0, $reportedAt->diffInSeconds($receivedAt))
            : null;

        $heartbeat = Heartbeat::query()->create([
            'device_id' => $device->id,
            'heartbeat_uuid' => $heartbeatUuid,
            'status' => $status,
            'diagnostic_status' => $diagnosticStatus,
            'internet_ok' => $internetOk,
            'dns_ok' => $dnsOk,
            'gateway_ok' => $gatewayOk,
            'monitoring_server_ok' => $monitoringServerOk,
            'latency_ms' => $validated['latency_ms'] ?? null,
            'dns_latency_ms' => $validated['dns_latency_ms'] ?? null,
            'ip_address' => $request->ip(),
            'agent_version' => $validated['agent_version'] ?? null,
            'network_info' => $validated['network_info'] ?? null,
            'checked_at' => $reportedAt,
            'received_at' => $receivedAt,
            'is_replayed' => $isReplay,
            'queue_delay_seconds' => $queueDelaySeconds,
            'payload' => $payload,
        ]);

        if (! $isReplay) {
            $device->forceFill([
                'status' => $status,
                'diagnostic_status' => $diagnosticStatus,
                'last_seen_at' => $receivedAt,
                'last_latency_ms' => $validated['latency_ms'] ?? null,
                'last_dns_latency_ms' => $validated['dns_latency_ms'] ?? null,
                'last_ip' => $request->ip(),
                'internet_ok' => $internetOk,
                'dns_ok' => $dnsOk,
                'gateway_ok' => $gatewayOk,
                'monitoring_server_ok' => $monitoringServerOk,
                'agent_version' => $validated['agent_version'] ?? $device->agent_version,
                'agent_health' => $validated['agent_health'] ?? $device->agent_health,
                'agent_health_updated_at' => array_key_exists('agent_health', $validated)
                    ? $receivedAt
                    : $device->agent_health_updated_at,
                'network_info' => $validated['network_info'] ?? $device->network_info,
                'network_info_updated_at' => array_key_exists('network_info', $validated)
                    ? $receivedAt
                    : $device->network_info_updated_at,
                'windows_update' => $validated['windows_update'] ?? $device->windows_update,
                'windows_update_updated_at' => array_key_exists('windows_update', $validated)
                    ? $receivedAt
                    : $device->windows_update_updated_at,
                'defender_status' => $validated['defender_status'] ?? $device->defender_status,
                'defender_status_updated_at' => array_key_exists('defender_status', $validated)
                    ? $receivedAt
                    : $device->defender_status_updated_at,
            ])->save();

            $currentDevice = $device->fresh();

            $windowsServices->process(
                $currentDevice,
                $validated['windows_services'] ?? [],
                $notifications
            );

            $smartDisks->process(
                $currentDevice,
                $validated['smart_info'] ?? [],
                $notifications
            );

            $windowsSecurity->process(
                $currentDevice,
                $validated['windows_update'] ?? null,
                $validated['defender_status'] ?? null,
                $notifications
            );

            $incidents->resolve(
                $currentDevice,
                'no_communication',
                'Komunikacja z agentem została przywrócona.',
                $notifications
            );

            $this->synchronizeDiagnosticIncident(
                $currentDevice,
                $diagnosticStatus,
                $notifications,
                $incidents
            );

            $enrollment->confirmByHeartbeat($currentDevice);
        }

        return response()->json([
            'ok' => true,
            'message' => $isReplay ? 'Queued heartbeat accepted.' : 'Heartbeat accepted.',
            'replayed' => $isReplay,
            'device_uuid' => $device->uuid,
            'device_status' => $device->fresh()->status,
            'diagnostic_status' => $diagnosticStatus,
            'heartbeat_id' => $heartbeat->id,
            'server_time' => $receivedAt->toIso8601String(),
        ]);
    }

    private function reportedAt(mixed $value, $fallback): CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return CarbonImmutable::instance($fallback);
        }

        try {
            $reported = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return CarbonImmutable::instance($fallback);
        }

        if ($reported->gt(CarbonImmutable::instance($fallback)->addMinutes(15))) {
            return CarbonImmutable::instance($fallback);
        }

        return $reported;
    }

    private function rules(): array
    {
        return [
            'heartbeat_uuid' => ['nullable', 'uuid'],
            'delivery' => ['nullable', 'array:is_replay,queued_at,attempt'],
            'delivery.is_replay' => ['nullable', 'boolean'],
            'delivery.queued_at' => ['nullable', 'date'],
            'delivery.attempt' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'internet_ok' => ['required', 'boolean'],
            'dns_ok' => ['required', 'boolean'],
            'gateway_ok' => ['nullable', 'boolean'],
            'monitoring_server_ok' => ['nullable', 'boolean'],
            'latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'dns_latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'diagnostic_status' => ['nullable', 'string', Rule::in([
                'online',
                'gateway_problem',
                'dns_problem',
                'internet_problem',
                'monitoring_server_problem',
            ])],
            'agent_version' => ['nullable', 'string', 'max:100'],
            'agent_health' => ['nullable', 'array:status,profile,run_mode,cycle_duration_ms,config_valid,state_file_writable,log_directory_writable,task_present,telemetry_completeness_percent,missing_modules,last_system_at,last_network_at,last_services_at,last_smart_at,consecutive_failures,last_failure_at,last_failure_reason,clock_skew_seconds,self_check_at'],
            'agent_health.status' => ['required_with:agent_health', 'string', Rule::in(['healthy', 'warning', 'critical', 'unknown'])],
            'agent_health.profile' => ['nullable', 'string', 'max:50'],
            'agent_health.run_mode' => ['nullable', 'string', Rule::in(['service', 'console'])],
            'agent_health.cycle_duration_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'agent_health.config_valid' => ['nullable', 'boolean'],
            'agent_health.state_file_writable' => ['nullable', 'boolean'],
            'agent_health.log_directory_writable' => ['nullable', 'boolean'],
            'agent_health.task_present' => ['nullable', 'boolean'],
            'agent_health.telemetry_completeness_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'agent_health.missing_modules' => ['nullable', 'array', 'max:10'],
            'agent_health.missing_modules.*' => ['string', Rule::in(['system', 'network', 'services', 'smart'])],
            'agent_health.last_system_at' => ['nullable', 'date'],
            'agent_health.last_network_at' => ['nullable', 'date'],
            'agent_health.last_services_at' => ['nullable', 'date'],
            'agent_health.last_smart_at' => ['nullable', 'date'],
            'agent_health.consecutive_failures' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'agent_health.last_failure_at' => ['nullable', 'date'],
            'agent_health.last_failure_reason' => ['nullable', 'string', 'max:500'],
            'agent_health.clock_skew_seconds' => ['nullable', 'integer', 'min:-86400', 'max:86400'],
            'agent_health.self_check_at' => ['nullable', 'date'],
            'checked_at' => ['nullable', 'date'],
            'location_code' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:255'],

            'test_details' => ['nullable', 'array', 'max:10'],
            'test_details.*' => ['array:url,ok,latency_ms,error'],
            'test_details.*.url' => ['required', 'url:http,https', 'max:500'],
            'test_details.*.ok' => ['required', 'boolean'],
            'test_details.*.latency_ms' => ['required', 'integer', 'min:0', 'max:600000'],
            'test_details.*.error' => ['nullable', 'string', 'max:1000'],

            'network_info' => ['nullable', 'array:connection_type,interface_alias,interface_description,interface_index,adapter_status,link_speed,mac_address,mtu,ipv4_address,ipv4_prefix_length,ipv6_addresses,default_gateway,dns_servers,dhcp_enabled,driver_version,driver_date,manufacturer,received_bytes,sent_bytes,received_errors,sent_errors,received_discards,sent_discards,wifi'],
            'network_info.connection_type' => ['nullable', 'string', 'max:50'],
            'network_info.interface_alias' => ['nullable', 'string', 'max:255'],
            'network_info.interface_description' => ['nullable', 'string', 'max:500'],
            'network_info.interface_index' => ['nullable', 'integer', 'min:0'],
            'network_info.adapter_status' => ['nullable', 'string', 'max:50'],
            'network_info.link_speed' => ['nullable', 'string', 'max:100'],
            'network_info.mac_address' => ['nullable', 'string', 'max:50'],
            'network_info.mtu' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'network_info.ipv4_address' => ['nullable', 'ipv4'],
            'network_info.ipv4_prefix_length' => ['nullable', 'integer', 'min:0', 'max:32'],
            'network_info.ipv6_addresses' => ['nullable', 'array', 'max:20'],
            'network_info.ipv6_addresses.*' => ['ipv6'],
            'network_info.default_gateway' => ['nullable', 'ip'],
            'network_info.dns_servers' => ['nullable', 'array', 'max:10'],
            'network_info.dns_servers.*' => ['ip'],
            'network_info.dhcp_enabled' => ['nullable', 'boolean'],
            'network_info.driver_version' => ['nullable', 'string', 'max:100'],
            'network_info.driver_date' => ['nullable', 'string', 'max:100'],
            'network_info.manufacturer' => ['nullable', 'string', 'max:255'],
            'network_info.received_bytes' => ['nullable', 'integer', 'min:0'],
            'network_info.sent_bytes' => ['nullable', 'integer', 'min:0'],
            'network_info.received_errors' => ['nullable', 'integer', 'min:0'],
            'network_info.sent_errors' => ['nullable', 'integer', 'min:0'],
            'network_info.received_discards' => ['nullable', 'integer', 'min:0'],
            'network_info.sent_discards' => ['nullable', 'integer', 'min:0'],
            'network_info.wifi' => ['nullable', 'array:ssid,bssid,signal_percent,radio_type,channel,receive_rate_mbps,transmit_rate_mbps'],
            'network_info.wifi.ssid' => ['nullable', 'string', 'max:255'],
            'network_info.wifi.bssid' => ['nullable', 'string', 'max:50'],
            'network_info.wifi.signal_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'network_info.wifi.radio_type' => ['nullable', 'string', 'max:100'],
            'network_info.wifi.channel' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'network_info.wifi.receive_rate_mbps' => ['nullable', 'numeric', 'min:0'],
            'network_info.wifi.transmit_rate_mbps' => ['nullable', 'numeric', 'min:0'],

            'windows_services' => ['nullable', 'array', 'max:50'],
            'windows_services.*' => ['array:name,label,display_name,exists,status,start_type,expected_status,alert,healthy,error'],
            'windows_services.*.name' => ['required', 'string', 'max:150'],
            'windows_services.*.label' => ['nullable', 'string', 'max:190'],
            'windows_services.*.display_name' => ['nullable', 'string', 'max:255'],
            'windows_services.*.exists' => ['required', 'boolean'],
            'windows_services.*.status' => ['required', 'string', 'max:50'],
            'windows_services.*.start_type' => ['nullable', 'string', 'max:50'],
            'windows_services.*.expected_status' => ['nullable', 'string', 'max:50'],
            'windows_services.*.alert' => ['nullable', 'boolean'],
            'windows_services.*.healthy' => ['nullable', 'boolean'],
            'windows_services.*.error' => ['nullable', 'string', 'max:1000'],

            'system_info' => ['nullable', 'array:cpu,memory,disks,uptime_seconds,boot_time,computer_name,os_caption,os_version'],
            'system_info.cpu' => ['nullable', 'array:usage_percent,logical_processors,cores,model'],
            'system_info.cpu.usage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'system_info.cpu.logical_processors' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'system_info.cpu.cores' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'system_info.cpu.model' => ['nullable', 'string', 'max:500'],
            'system_info.memory' => ['nullable', 'array:total_bytes,used_bytes,free_bytes,usage_percent'],
            'system_info.memory.total_bytes' => ['nullable', 'integer', 'min:0'],
            'system_info.memory.used_bytes' => ['nullable', 'integer', 'min:0'],
            'system_info.memory.free_bytes' => ['nullable', 'integer', 'min:0'],
            'system_info.memory.usage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'system_info.disks' => ['nullable', 'array', 'max:50'],
            'system_info.disks.*' => ['array:name,label,filesystem,total_bytes,used_bytes,free_bytes,usage_percent'],
            'system_info.disks.*.name' => ['required', 'string', 'max:100'],
            'system_info.disks.*.label' => ['nullable', 'string', 'max:255'],
            'system_info.disks.*.filesystem' => ['nullable', 'string', 'max:50'],
            'system_info.disks.*.total_bytes' => ['nullable', 'integer', 'min:0'],
            'system_info.disks.*.used_bytes' => ['nullable', 'integer', 'min:0'],
            'system_info.disks.*.free_bytes' => ['nullable', 'integer', 'min:0'],
            'system_info.disks.*.usage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'system_info.uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'system_info.boot_time' => ['nullable', 'date'],
            'system_info.computer_name' => ['nullable', 'string', 'max:255'],
            'system_info.os_caption' => ['nullable', 'string', 'max:500'],
            'system_info.os_version' => ['nullable', 'string', 'max:100'],

            'windows_update' => ['nullable', 'array:available,collected_at,last_installed_update_at,last_hotfix_id,pending_updates_count,pending_critical_count,pending_security_count,restart_required,service_status,error'],
            'windows_update.available' => ['required_with:windows_update', 'boolean'],
            'windows_update.collected_at' => ['nullable', 'date'],
            'windows_update.last_installed_update_at' => ['nullable', 'date'],
            'windows_update.last_hotfix_id' => ['nullable', 'string', 'max:100'],
            'windows_update.pending_updates_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'windows_update.pending_critical_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'windows_update.pending_security_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'windows_update.restart_required' => ['nullable', 'boolean'],
            'windows_update.service_status' => ['nullable', 'string', 'max:100'],
            'windows_update.error' => ['nullable', 'string', 'max:1000'],

            'defender_status' => ['nullable', 'array:available,collected_at,am_running_mode,antivirus_enabled,antimalware_service_enabled,real_time_protection_enabled,behavior_monitor_enabled,ioav_protection_enabled,nis_enabled,tamper_protected,signature_age_days,signature_last_updated,quick_scan_age_days,quick_scan_end_time,full_scan_age_days,full_scan_end_time,active_threat_count,antivirus_products,firewall_profiles,error'],
            'defender_status.available' => ['required_with:defender_status', 'boolean'],
            'defender_status.collected_at' => ['nullable', 'date'],
            'defender_status.am_running_mode' => ['nullable', 'string', 'max:100'],
            'defender_status.antivirus_enabled' => ['nullable', 'boolean'],
            'defender_status.antimalware_service_enabled' => ['nullable', 'boolean'],
            'defender_status.real_time_protection_enabled' => ['nullable', 'boolean'],
            'defender_status.behavior_monitor_enabled' => ['nullable', 'boolean'],
            'defender_status.ioav_protection_enabled' => ['nullable', 'boolean'],
            'defender_status.nis_enabled' => ['nullable', 'boolean'],
            'defender_status.tamper_protected' => ['nullable', 'boolean'],
            'defender_status.signature_age_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'defender_status.signature_last_updated' => ['nullable', 'date'],
            'defender_status.quick_scan_age_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'defender_status.quick_scan_end_time' => ['nullable', 'date'],
            'defender_status.full_scan_age_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'defender_status.full_scan_end_time' => ['nullable', 'date'],
            'defender_status.active_threat_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'defender_status.antivirus_products' => ['nullable', 'array', 'max:20'],
            'defender_status.antivirus_products.*' => ['array:display_name,product_state,state_hex,enabled,up_to_date,path_to_signed_product_exe'],
            'defender_status.antivirus_products.*.display_name' => ['required', 'string', 'max:255'],
            'defender_status.antivirus_products.*.product_state' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'defender_status.antivirus_products.*.state_hex' => ['nullable', 'string', 'max:20'],
            'defender_status.antivirus_products.*.enabled' => ['nullable', 'boolean'],
            'defender_status.antivirus_products.*.up_to_date' => ['nullable', 'boolean'],
            'defender_status.antivirus_products.*.path_to_signed_product_exe' => ['nullable', 'string', 'max:1000'],
            'defender_status.firewall_profiles' => ['nullable', 'array', 'max:10'],
            'defender_status.firewall_profiles.*' => ['array:name,enabled,default_inbound_action,default_outbound_action'],
            'defender_status.firewall_profiles.*.name' => ['required', 'string', 'max:100'],
            'defender_status.firewall_profiles.*.enabled' => ['nullable', 'boolean'],
            'defender_status.firewall_profiles.*.default_inbound_action' => ['nullable', 'string', 'max:50'],
            'defender_status.firewall_profiles.*.default_outbound_action' => ['nullable', 'string', 'max:50'],
            'defender_status.error' => ['nullable', 'string', 'max:1000'],

            'smart_info' => ['nullable', 'array:disks,collected_at'],
            'smart_info.collected_at' => ['nullable', 'date'],
            'smart_info.disks' => ['nullable', 'array', 'max:20'],
            'smart_info.disks.*' => ['array:device_id,friendly_name,model,media_type,bus_type,size_bytes,health_status,operational_status,temperature_c,max_temperature_c,wear_percent_used,power_on_hours,read_errors_total,write_errors_total,predict_failure,smart_supported,status_message'],
            'smart_info.disks.*.device_id' => ['nullable', 'string', 'max:100'],
            'smart_info.disks.*.friendly_name' => ['nullable', 'string', 'max:255'],
            'smart_info.disks.*.model' => ['nullable', 'string', 'max:255'],
            'smart_info.disks.*.media_type' => ['nullable', 'string', 'max:100'],
            'smart_info.disks.*.bus_type' => ['nullable', 'string', 'max:100'],
            'smart_info.disks.*.size_bytes' => ['nullable', 'integer', 'min:0'],
            'smart_info.disks.*.health_status' => ['nullable', 'string', 'max:50'],
            'smart_info.disks.*.operational_status' => ['nullable', 'array', 'max:20'],
            'smart_info.disks.*.operational_status.*' => ['string', 'max:100'],
            'smart_info.disks.*.temperature_c' => ['nullable', 'numeric', 'min:-50', 'max:200'],
            'smart_info.disks.*.max_temperature_c' => ['nullable', 'numeric', 'min:-50', 'max:200'],
            'smart_info.disks.*.wear_percent_used' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'smart_info.disks.*.power_on_hours' => ['nullable', 'integer', 'min:0'],
            'smart_info.disks.*.read_errors_total' => ['nullable', 'integer', 'min:0'],
            'smart_info.disks.*.write_errors_total' => ['nullable', 'integer', 'min:0'],
            'smart_info.disks.*.predict_failure' => ['required', 'boolean'],
            'smart_info.disks.*.smart_supported' => ['required', 'boolean'],
            'smart_info.disks.*.status_message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Device authentication failed.',
        ], 401);
    }

    private function determineDiagnosticStatus(
        ?bool $gatewayOk,
        bool $dnsOk,
        bool $internetOk,
        ?bool $monitoringServerOk
    ): string {
        if ($gatewayOk === false) {
            return 'gateway_problem';
        }

        if (! $dnsOk) {
            return 'dns_problem';
        }

        if (! $internetOk) {
            return 'internet_problem';
        }

        if ($monitoringServerOk === false) {
            return 'monitoring_server_problem';
        }

        return 'online';
    }

    private function synchronizeDiagnosticIncident(
        Device $device,
        string $diagnosticStatus,
        IncidentNotificationService $notifications,
        IncidentLifecycleService $incidents
    ): void {
        foreach (self::DIAGNOSTIC_INCIDENT_TYPES as $type) {
            if ($type !== $diagnosticStatus) {
                $incidents->resolve(
                    $device,
                    $type,
                    'Test diagnostyczny wrócił do stanu prawidłowego.',
                    $notifications
                );
            }
        }

        if ($diagnosticStatus === 'online') {
            return;
        }

        $incidents->openOrTouch(
            $device,
            $diagnosticStatus,
            $this->diagnosticSummary($diagnosticStatus),
            $incidents->priorityForType($diagnosticStatus),
            $notifications
        );
    }

    private function diagnosticSummary(string $type): string
    {
        return match ($type) {
            'gateway_problem' => 'Agent działa, ale nie może połączyć się z bramą domyślną lub routerem.',
            'dns_problem' => 'Połączenie lokalne działa, ale test rozwiązywania nazw DNS zakończył się błędem.',
            'monitoring_server_problem' => 'Internet działa, ale test dostępu do serwera Placówka Online zakończył się błędem.',
            default => 'Brama i DNS działają, ale testy dostępu do Internetu zakończyły się błędem.',
        };
    }
}
