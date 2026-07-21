package main

import (
	"bytes"
	"context"
	"crypto/rand"
	"crypto/tls"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"hash/fnv"
	"io"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"sort"
	"strings"
	"sync"
	"syscall"
	"time"
	"unsafe"
)

const builtInVersion = "exe-1.9.2"

var runMode = "console"

type Config struct {
	APIURL                       string                 `json:"api_url"`
	MonitoringURL                string                 `json:"monitoring_url"`
	Token                        string                 `json:"token"`
	AgentVersion                 string                 `json:"agent_version"`
	LocationCode                 string                 `json:"location_code"`
	DeviceName                   string                 `json:"device_name"`
	TimeoutSeconds               int                    `json:"timeout_seconds"`
	PerformanceProfile           string                 `json:"performance_profile"`
	SystemIntervalMinutes        int                    `json:"system_interval_minutes"`
	NetworkIntervalMinutes       int                    `json:"network_interval_minutes"`
	ServicesIntervalMinutes      int                    `json:"services_interval_minutes"`
	SmartIntervalMinutes         int                    `json:"smart_interval_minutes"`
	SelfCheckIntervalMinutes     int                    `json:"self_check_interval_minutes"`
	WindowsUpdateIntervalMinutes int                    `json:"windows_update_interval_minutes"`
	DefenderIntervalMinutes      int                    `json:"defender_interval_minutes"`
	OfflineQueueMaxItems         int                    `json:"offline_queue_max_items"`
	OfflineQueueMaxAgeDays       int                    `json:"offline_queue_max_age_days"`
	OfflineQueueFlushPerCycle    int                    `json:"offline_queue_flush_per_cycle"`
	StartupJitterSeconds         int                    `json:"startup_jitter_seconds"`
	TestURLs                     []string               `json:"test_urls"`
	DNSTestHost                  string                 `json:"dns_test_host"`
	WindowsServices              []WindowsServiceConfig `json:"windows_services"`
}

type WindowsServiceConfig struct {
	Name           string `json:"name"`
	Label          string `json:"label"`
	ExpectedStatus string `json:"expected_status"`
	Alert          bool   `json:"alert"`
}

type WindowsServiceStatus struct {
	Name           string `json:"name"`
	Label          string `json:"label"`
	DisplayName    string `json:"display_name,omitempty"`
	Exists         bool   `json:"exists"`
	Status         string `json:"status"`
	StartType      string `json:"start_type,omitempty"`
	ExpectedStatus string `json:"expected_status"`
	Alert          bool   `json:"alert"`
	Healthy        bool   `json:"healthy"`
	Error          string `json:"error,omitempty"`
}

type CPUInfo struct {
	UsagePercent      *float64 `json:"usage_percent,omitempty"`
	LogicalProcessors *int     `json:"logical_processors,omitempty"`
	Cores             *int     `json:"cores,omitempty"`
	Model             string   `json:"model,omitempty"`
}

type MemoryInfo struct {
	TotalBytes   *int64   `json:"total_bytes,omitempty"`
	UsedBytes    *int64   `json:"used_bytes,omitempty"`
	FreeBytes    *int64   `json:"free_bytes,omitempty"`
	UsagePercent *float64 `json:"usage_percent,omitempty"`
}

type DiskInfo struct {
	Name         string   `json:"name"`
	Label        string   `json:"label,omitempty"`
	FileSystem   string   `json:"filesystem,omitempty"`
	TotalBytes   *int64   `json:"total_bytes,omitempty"`
	UsedBytes    *int64   `json:"used_bytes,omitempty"`
	FreeBytes    *int64   `json:"free_bytes,omitempty"`
	UsagePercent *float64 `json:"usage_percent,omitempty"`
}

type SystemInfo struct {
	CPU           *CPUInfo    `json:"cpu,omitempty"`
	Memory        *MemoryInfo `json:"memory,omitempty"`
	Disks         []DiskInfo  `json:"disks,omitempty"`
	UptimeSeconds *int64      `json:"uptime_seconds,omitempty"`
	BootTime      string      `json:"boot_time,omitempty"`
	ComputerName  string      `json:"computer_name,omitempty"`
	OSCaption     string      `json:"os_caption,omitempty"`
	OSVersion     string      `json:"os_version,omitempty"`
}

type SmartDiskInfo struct {
	DeviceID          string   `json:"device_id,omitempty"`
	FriendlyName      string   `json:"friendly_name,omitempty"`
	Model             string   `json:"model,omitempty"`
	MediaType         string   `json:"media_type,omitempty"`
	BusType           string   `json:"bus_type,omitempty"`
	SizeBytes         *int64   `json:"size_bytes,omitempty"`
	HealthStatus      string   `json:"health_status,omitempty"`
	OperationalStatus []string `json:"operational_status,omitempty"`
	TemperatureC      *float64 `json:"temperature_c,omitempty"`
	MaxTemperatureC   *float64 `json:"max_temperature_c,omitempty"`
	WearPercentUsed   *float64 `json:"wear_percent_used,omitempty"`
	PowerOnHours      *int64   `json:"power_on_hours,omitempty"`
	ReadErrorsTotal   *int64   `json:"read_errors_total,omitempty"`
	WriteErrorsTotal  *int64   `json:"write_errors_total,omitempty"`
	PredictFailure    bool     `json:"predict_failure"`
	SmartSupported    bool     `json:"smart_supported"`
	StatusMessage     string   `json:"status_message,omitempty"`
}

type SmartInfo struct {
	Disks       []SmartDiskInfo `json:"disks,omitempty"`
	CollectedAt string          `json:"collected_at,omitempty"`
}

type TestDetail struct {
	URL       string `json:"url"`
	OK        bool   `json:"ok"`
	LatencyMS int64  `json:"latency_ms"`
	Error     string `json:"error,omitempty"`
}
type WiFiInfo struct {
	SSID             string   `json:"ssid,omitempty"`
	BSSID            string   `json:"bssid,omitempty"`
	SignalPercent    *int     `json:"signal_percent,omitempty"`
	RadioType        string   `json:"radio_type,omitempty"`
	Channel          *int     `json:"channel,omitempty"`
	ReceiveRateMbps  *float64 `json:"receive_rate_mbps,omitempty"`
	TransmitRateMbps *float64 `json:"transmit_rate_mbps,omitempty"`
}
type NetworkInfo struct {
	ConnectionType       string    `json:"connection_type,omitempty"`
	InterfaceAlias       string    `json:"interface_alias,omitempty"`
	InterfaceDescription string    `json:"interface_description,omitempty"`
	InterfaceIndex       *int      `json:"interface_index,omitempty"`
	AdapterStatus        string    `json:"adapter_status,omitempty"`
	LinkSpeed            string    `json:"link_speed,omitempty"`
	MACAddress           string    `json:"mac_address,omitempty"`
	MTU                  *int      `json:"mtu,omitempty"`
	IPv4Address          string    `json:"ipv4_address,omitempty"`
	IPv4PrefixLength     *int      `json:"ipv4_prefix_length,omitempty"`
	IPv6Addresses        []string  `json:"ipv6_addresses,omitempty"`
	DefaultGateway       string    `json:"default_gateway,omitempty"`
	DNSServers           []string  `json:"dns_servers,omitempty"`
	DHCPEnabled          *bool     `json:"dhcp_enabled,omitempty"`
	DriverVersion        string    `json:"driver_version,omitempty"`
	DriverDate           string    `json:"driver_date,omitempty"`
	Manufacturer         string    `json:"manufacturer,omitempty"`
	ReceivedBytes        *int64    `json:"received_bytes,omitempty"`
	SentBytes            *int64    `json:"sent_bytes,omitempty"`
	ReceivedErrors       *int64    `json:"received_errors,omitempty"`
	SentErrors           *int64    `json:"sent_errors,omitempty"`
	ReceivedDiscards     *int64    `json:"received_discards,omitempty"`
	SentDiscards         *int64    `json:"sent_discards,omitempty"`
	WiFi                 *WiFiInfo `json:"wifi,omitempty"`
}
type AgentHealth struct {
	Status                       string   `json:"status"`
	Profile                      string   `json:"profile"`
	RunMode                      string   `json:"run_mode"`
	CycleDurationMS              int64    `json:"cycle_duration_ms"`
	ConfigValid                  bool     `json:"config_valid"`
	StateFileWritable            bool     `json:"state_file_writable"`
	LogDirectoryWritable         bool     `json:"log_directory_writable"`
	TaskPresent                  *bool    `json:"task_present,omitempty"`
	TelemetryCompletenessPercent int      `json:"telemetry_completeness_percent"`
	MissingModules               []string `json:"missing_modules,omitempty"`
	LastSystemAt                 string   `json:"last_system_at,omitempty"`
	LastNetworkAt                string   `json:"last_network_at,omitempty"`
	LastServicesAt               string   `json:"last_services_at,omitempty"`
	LastSmartAt                  string   `json:"last_smart_at,omitempty"`
	ConsecutiveFailures          int      `json:"consecutive_failures"`
	LastFailureAt                string   `json:"last_failure_at,omitempty"`
	LastFailureReason            string   `json:"last_failure_reason,omitempty"`
	ClockSkewSeconds             *int64   `json:"clock_skew_seconds,omitempty"`
	SelfCheckAt                  string   `json:"self_check_at,omitempty"`
}

type DeliveryInfo struct {
	IsReplay bool   `json:"is_replay"`
	QueuedAt string `json:"queued_at,omitempty"`
	Attempt  int    `json:"attempt,omitempty"`
}

type WindowsUpdateInfo struct {
	Available             bool   `json:"available"`
	CollectedAt           string `json:"collected_at,omitempty"`
	LastInstalledUpdateAt string `json:"last_installed_update_at,omitempty"`
	LastHotfixID          string `json:"last_hotfix_id,omitempty"`
	PendingUpdatesCount   int    `json:"pending_updates_count"`
	PendingCriticalCount  int    `json:"pending_critical_count"`
	PendingSecurityCount  int    `json:"pending_security_count"`
	RestartRequired       bool   `json:"restart_required"`
	ServiceStatus         string `json:"service_status,omitempty"`
	Error                 string `json:"error,omitempty"`
}

type FirewallProfile struct {
	Name                  string `json:"name"`
	Enabled               *bool  `json:"enabled,omitempty"`
	DefaultInboundAction  string `json:"default_inbound_action,omitempty"`
	DefaultOutboundAction string `json:"default_outbound_action,omitempty"`
}

type AntivirusProduct struct {
	DisplayName            string `json:"display_name"`
	ProductState           uint32 `json:"product_state"`
	StateHex               string `json:"state_hex,omitempty"`
	Enabled                *bool  `json:"enabled,omitempty"`
	UpToDate               *bool  `json:"up_to_date,omitempty"`
	PathToSignedProductExe string `json:"path_to_signed_product_exe,omitempty"`
}

type DefenderStatus struct {
	Available                 bool               `json:"available"`
	CollectedAt               string             `json:"collected_at,omitempty"`
	AMRunningMode             string             `json:"am_running_mode,omitempty"`
	AntivirusEnabled          *bool              `json:"antivirus_enabled,omitempty"`
	AntimalwareServiceEnabled *bool              `json:"antimalware_service_enabled,omitempty"`
	RealTimeProtectionEnabled *bool              `json:"real_time_protection_enabled,omitempty"`
	BehaviorMonitorEnabled    *bool              `json:"behavior_monitor_enabled,omitempty"`
	IOAVProtectionEnabled     *bool              `json:"ioav_protection_enabled,omitempty"`
	NISEnabled                *bool              `json:"nis_enabled,omitempty"`
	TamperProtected           *bool              `json:"tamper_protected,omitempty"`
	SignatureAgeDays          *int               `json:"signature_age_days,omitempty"`
	SignatureLastUpdated      string             `json:"signature_last_updated,omitempty"`
	QuickScanAgeDays          *int               `json:"quick_scan_age_days,omitempty"`
	QuickScanEndTime          string             `json:"quick_scan_end_time,omitempty"`
	FullScanAgeDays           *int               `json:"full_scan_age_days,omitempty"`
	FullScanEndTime           string             `json:"full_scan_end_time,omitempty"`
	ActiveThreatCount         int                `json:"active_threat_count"`
	AntivirusProducts         []AntivirusProduct `json:"antivirus_products,omitempty"`
	FirewallProfiles          []FirewallProfile  `json:"firewall_profiles,omitempty"`
	Error                     string             `json:"error,omitempty"`
}

type HeartbeatPayload struct {
	HeartbeatUUID      string                 `json:"heartbeat_uuid"`
	Delivery           *DeliveryInfo          `json:"delivery,omitempty"`
	InternetOK         bool                   `json:"internet_ok"`
	DNSOK              bool                   `json:"dns_ok"`
	GatewayOK          *bool                  `json:"gateway_ok"`
	MonitoringServerOK *bool                  `json:"monitoring_server_ok"`
	LatencyMS          *int64                 `json:"latency_ms"`
	DNSLatencyMS       *int64                 `json:"dns_latency_ms"`
	DiagnosticStatus   string                 `json:"diagnostic_status"`
	AgentVersion       string                 `json:"agent_version"`
	AgentHealth        *AgentHealth           `json:"agent_health,omitempty"`
	CheckedAt          string                 `json:"checked_at"`
	LocationCode       string                 `json:"location_code,omitempty"`
	DeviceName         string                 `json:"device_name,omitempty"`
	TestDetails        []TestDetail           `json:"test_details,omitempty"`
	NetworkInfo        *NetworkInfo           `json:"network_info,omitempty"`
	WindowsServices    []WindowsServiceStatus `json:"windows_services,omitempty"`
	SystemInfo         *SystemInfo            `json:"system_info,omitempty"`
	SmartInfo          *SmartInfo             `json:"smart_info,omitempty"`
	WindowsUpdate      *WindowsUpdateInfo     `json:"windows_update,omitempty"`
	DefenderStatus     *DefenderStatus        `json:"defender_status,omitempty"`
}
type InternetResult struct {
	InternetOK   bool
	AvgLatencyMS *int64
	Details      []TestDetail
}
type LogEntry struct {
	Time    string      `json:"time"`
	Level   string      `json:"level"`
	Message string      `json:"message"`
	Data    interface{} `json:"data,omitempty"`
}

// AgentState przechowuje ostatnie cięższe pomiary. Dzięki temu heartbeat może
// działać co minutę bez uruchamiania pełnych zapytań PowerShell przy każdym cyklu.
type AgentState struct {
	Version              int                    `json:"version"`
	StartedAt            string                 `json:"started_at"`
	LastSystemAt         string                 `json:"last_system_at,omitempty"`
	LastNetworkAt        string                 `json:"last_network_at,omitempty"`
	LastServicesAt       string                 `json:"last_services_at,omitempty"`
	LastSmartAt          string                 `json:"last_smart_at,omitempty"`
	LastLogCleanupAt     string                 `json:"last_log_cleanup_at,omitempty"`
	LastSuccessLogAt     string                 `json:"last_success_log_at,omitempty"`
	LastSelfCheckAt      string                 `json:"last_self_check_at,omitempty"`
	LastWindowsUpdateAt  string                 `json:"last_windows_update_at,omitempty"`
	LastDefenderAt       string                 `json:"last_defender_at,omitempty"`
	ConsecutiveFailures  int                    `json:"consecutive_failures"`
	LastFailureAt        string                 `json:"last_failure_at,omitempty"`
	LastFailureReason    string                 `json:"last_failure_reason,omitempty"`
	LastClockSkewSeconds *int64                 `json:"last_clock_skew_seconds,omitempty"`
	AgentHealth          *AgentHealth           `json:"agent_health,omitempty"`
	SystemInfo           *SystemInfo            `json:"system_info,omitempty"`
	NetworkInfo          *NetworkInfo           `json:"network_info,omitempty"`
	WindowsServices      []WindowsServiceStatus `json:"windows_services,omitempty"`
	SmartInfo            *SmartInfo             `json:"smart_info,omitempty"`
	WindowsUpdate        *WindowsUpdateInfo     `json:"windows_update,omitempty"`
	DefenderStatus       *DefenderStatus        `json:"defender_status,omitempty"`
}

func main() {
	if err := run(); err != nil {
		fmt.Println("ERROR:", friendlyError(err))
		os.Exit(1)
	}
}
func run() error {
	started := time.Now()
	setBelowNormalPriority()

	baseDir, err := executableDir()
	if err != nil {
		baseDir = `C:\PlacowkaOnline`
	}

	logDir := filepath.Join(baseDir, "logs")
	queueDir := filepath.Join(baseDir, "queue")
	statePath := filepath.Join(baseDir, "state.json")
	_ = os.MkdirAll(logDir, 0755)
	_ = os.MkdirAll(queueDir, 0700)

	cfg, err := loadConfig(filepath.Join(baseDir, "config.json"))
	if err != nil {
		return err
	}
	if err := validateConfig(cfg); err != nil {
		return err
	}
	normalizePerformanceConfig(&cfg)

	if runMode == "service" && cfg.StartupJitterSeconds > 0 {
		time.Sleep(deterministicJitter(cfg, cfg.StartupJitterSeconds))
	}

	state := loadState(statePath)
	now := time.Now().UTC()
	stateDirty := false
	if state.StartedAt == "" {
		state.StartedAt = now.Format(time.RFC3339)
		stateDirty = true
	}
	state.Version = 3

	if isDue(state.LastLogCleanupAt, 24*time.Hour, now) {
		cleanupOldLogs(logDir, 14*24*time.Hour)
		state.LastLogCleanupAt = now.Format(time.RFC3339)
		stateDirty = true
	}

	consoleRun := runMode == "console"
	age := elapsedSince(state.StartedAt, now)
	collected := make([]string, 0, 4)

	refreshNetwork := consoleRun || isDue(
		state.LastNetworkAt,
		time.Duration(cfg.NetworkIntervalMinutes)*time.Minute,
		now,
	)
	if refreshNetwork {
		state.LastNetworkAt = now.Format(time.RFC3339)
		stateDirty = true
		if info := collectNetworkInfo(time.Duration(cfg.TimeoutSeconds) * time.Second); info != nil {
			state.NetworkInfo = info
			collected = append(collected, "network")
		}
	}

	refreshSystem := consoleRun || (age >= time.Minute && isDue(
		state.LastSystemAt,
		time.Duration(cfg.SystemIntervalMinutes)*time.Minute,
		now,
	))
	if refreshSystem {
		state.LastSystemAt = now.Format(time.RFC3339)
		stateDirty = true
		if info := collectSystemInfo(time.Duration(cfg.TimeoutSeconds) * time.Second); info != nil {
			state.SystemInfo = info
			collected = append(collected, "system_inventory")
		}
	}

	// Usługi są celowo opóźnione po pierwszej instalacji, aby nie kumulować
	// wszystkich cięższych pomiarów w pierwszym cyklu.
	refreshServices := consoleRun || (age >= 2*time.Minute && isDue(
		state.LastServicesAt,
		time.Duration(cfg.ServicesIntervalMinutes)*time.Minute,
		now,
	))
	if refreshServices {
		state.LastServicesAt = now.Format(time.RFC3339)
		stateDirty = true
		if len(cfg.WindowsServices) > 0 {
			state.WindowsServices = collectWindowsServices(
				cfg.WindowsServices,
				time.Duration(cfg.TimeoutSeconds)*time.Second,
			)
			collected = append(collected, "windows_services")
		}
	}

	// SMART jest najcięższym pomiarem i nie wymaga wykonywania co minutę.
	refreshSmart := consoleRun || (age >= 5*time.Minute && isDue(
		state.LastSmartAt,
		time.Duration(cfg.SmartIntervalMinutes)*time.Minute,
		now,
	))
	if refreshSmart {
		state.LastSmartAt = now.Format(time.RFC3339)
		stateDirty = true
		if info := collectSmartInfo(time.Duration(cfg.TimeoutSeconds) * time.Second); info != nil {
			state.SmartInfo = info
			collected = append(collected, "smart")
		}
	}

	refreshWindowsUpdate := consoleRun || (age >= 3*time.Minute && isDue(
		state.LastWindowsUpdateAt,
		time.Duration(cfg.WindowsUpdateIntervalMinutes)*time.Minute,
		now,
	))
	if refreshWindowsUpdate {
		state.LastWindowsUpdateAt = now.Format(time.RFC3339)
		stateDirty = true
		if info := collectWindowsUpdateInfo(time.Duration(cfg.TimeoutSeconds) * time.Second); info != nil {
			state.WindowsUpdate = info
			collected = append(collected, "windows_update")
		}
	}

	refreshDefender := consoleRun || (age >= 4*time.Minute && isDue(
		state.LastDefenderAt,
		time.Duration(cfg.DefenderIntervalMinutes)*time.Minute,
		now,
	))
	if refreshDefender {
		state.LastDefenderAt = now.Format(time.RFC3339)
		stateDirty = true
		if info := collectDefenderStatus(time.Duration(cfg.TimeoutSeconds) * time.Second); info != nil {
			state.DefenderStatus = info
			collected = append(collected, "defender")
		}
	}

	refreshSelfCheck := consoleRun || state.AgentHealth == nil ||
		(runMode == "service" && state.AgentHealth.TaskPresent == nil) ||
		isDue(state.LastSelfCheckAt, time.Duration(cfg.SelfCheckIntervalMinutes)*time.Minute, now)
	if refreshSelfCheck {
		state.LastSelfCheckAt = now.Format(time.RFC3339)
		health := performAgentSelfCheck(baseDir, logDir, statePath, runMode)
		state.AgentHealth = &health
		stateDirty = true
	}

	// CPU, RAM i uptime są pobierane przez lekkie wywołania WinAPI, bez WMI/CIM.
	state.SystemInfo = mergeSystemInfo(state.SystemInfo, collectLightweightSystemInfo())

	gatewayOK, dnsOK, dnsLatency, internet, monitoringOK := collectConnectivity(
		cfg,
		state.NetworkInfo,
	)

	// Gdy wcześniej zapamiętana brama przestała odpowiadać, odświeżamy dane
	// sieciowe od razu, zamiast czekać do kolejnego interwału pełnego pomiaru.
	if gatewayOK != nil && !*gatewayOK && !refreshNetwork {
		state.LastNetworkAt = now.Format(time.RFC3339)
		stateDirty = true
		if info := collectNetworkInfo(time.Duration(cfg.TimeoutSeconds) * time.Second); info != nil {
			state.NetworkInfo = info
			gatewayOK = testDefaultGateway(info, time.Duration(cfg.TimeoutSeconds)*time.Second)
			collected = append(collected, "network_recovery")
		}
	}

	diagnostic := determineDiagnosticStatus(
		gatewayOK,
		dnsOK,
		internet.InternetOK,
		monitoringOK,
	)

	agentHealth := buildAgentHealth(cfg, state, started, now)

	payload := HeartbeatPayload{
		HeartbeatUUID:      newHeartbeatUUID(),
		InternetOK:         internet.InternetOK,
		DNSOK:              dnsOK,
		GatewayOK:          gatewayOK,
		MonitoringServerOK: monitoringOK,
		LatencyMS:          internet.AvgLatencyMS,
		DNSLatencyMS:       dnsLatency,
		DiagnosticStatus:   diagnostic,
		AgentVersion:       cfg.AgentVersion,
		AgentHealth:        &agentHealth,
		CheckedAt:          now.Format(time.RFC3339),
		LocationCode:       cfg.LocationCode,
		DeviceName:         cfg.DeviceName,
		TestDetails:        internet.Details,
		NetworkInfo:        state.NetworkInfo,
		WindowsServices:    state.WindowsServices,
		SystemInfo:         state.SystemInfo,
		SmartInfo:          state.SmartInfo,
		WindowsUpdate:      state.WindowsUpdate,
		DefenderStatus:     state.DefenderStatus,
	}

	// Stan zapisujemy tylko po odświeżeniu cięższego modułu lub metadanych.
	// Dzięki temu zwykły heartbeat nie wykonuje zapisu na dysku co minutę.
	if stateDirty {
		_ = saveState(statePath, state)
	}

	cleanupHeartbeatQueue(queueDir, cfg.OfflineQueueMaxItems, time.Duration(cfg.OfflineQueueMaxAgeDays)*24*time.Hour)
	flushed, flushErr := flushHeartbeatQueue(cfg, queueDir, cfg.OfflineQueueFlushPerCycle)
	if flushErr != nil {
		writeLog(logDir, "WARNING", "Offline heartbeat queue flush paused", map[string]any{
			"error":   flushErr.Error(),
			"flushed": flushed,
		})
	}

	body, status, err := sendHeartbeat(cfg, payload)
	if err != nil || status < 200 || status >= 300 {
		if err == nil {
			err = fmt.Errorf("server returned HTTP %d: %s", status, strings.TrimSpace(body))
		}
		queueErr := queueHeartbeat(queueDir, payload)
		recordHeartbeatFailure(&state, now, err.Error())
		_ = saveState(statePath, state)
		writeLog(logDir, "ERROR", "Heartbeat send failed; payload queued", map[string]any{
			"error":             err.Error(),
			"queue_error":       errorText(queueErr),
			"diagnostic_status": diagnostic,
			"duration_ms":       time.Since(started).Milliseconds(),
		})
		return err
	}

	stateChangedAfterSend := state.ConsecutiveFailures > 0 || state.LastClockSkewSeconds == nil
	state.ConsecutiveFailures = 0
	if skew, ok := responseClockSkew(body, time.Now().UTC()); ok {
		if state.LastClockSkewSeconds == nil || absInt64(*state.LastClockSkewSeconds-skew) >= 5 {
			stateChangedAfterSend = true
		}
		state.LastClockSkewSeconds = &skew
	}
	if stateChangedAfterSend {
		_ = saveState(statePath, state)
	}

	// Sukces zapisujemy najwyżej raz na 6 godzin, zamiast dopisywać log co minutę.
	if isDue(state.LastSuccessLogAt, 6*time.Hour, now) {
		state.LastSuccessLogAt = now.Format(time.RFC3339)
		writeLog(logDir, "INFO", "Heartbeat sent successfully", map[string]any{
			"http_status":       status,
			"diagnostic_status": diagnostic,
			"duration_ms":       time.Since(started).Milliseconds(),
			"refreshed_modules": collected,
			"offline_flushed":   flushed,
		})
		_ = saveState(statePath, state)
	}

	if consoleRun {
		fmt.Println("OK: Heartbeat sent. Diagnosis:", diagnostic)
		fmt.Println("Duration:", time.Since(started).Round(time.Millisecond))
		fmt.Println("Refreshed modules:", strings.Join(collected, ", "))
		fmt.Println(prettyJSON(body))
	}

	return nil
}

func newHeartbeatUUID() string {
	buffer := make([]byte, 16)
	if _, err := rand.Read(buffer); err != nil {
		seed := fmt.Sprintf("%d-%d-%s", time.Now().UnixNano(), os.Getpid(), builtInVersion)
		sum := fnv.New128a()
		_, _ = sum.Write([]byte(seed))
		copy(buffer, sum.Sum(nil))
	}
	buffer[6] = (buffer[6] & 0x0f) | 0x40
	buffer[8] = (buffer[8] & 0x3f) | 0x80
	hexValue := hex.EncodeToString(buffer)
	return fmt.Sprintf("%s-%s-%s-%s-%s", hexValue[0:8], hexValue[8:12], hexValue[12:16], hexValue[16:20], hexValue[20:32])
}

func queueHeartbeat(queueDir string, payload HeartbeatPayload) error {
	if strings.TrimSpace(payload.HeartbeatUUID) == "" {
		payload.HeartbeatUUID = newHeartbeatUUID()
	}
	payload.Delivery = &DeliveryInfo{IsReplay: true, QueuedAt: time.Now().UTC().Format(time.RFC3339), Attempt: 1}
	raw, err := json.Marshal(payload)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(queueDir, 0700); err != nil {
		return err
	}
	path := filepath.Join(queueDir, payload.CheckedAt+"_"+payload.HeartbeatUUID+".json")
	path = strings.ReplaceAll(path, ":", "-")
	return os.WriteFile(path, raw, 0600)
}

func flushHeartbeatQueue(cfg Config, queueDir string, maximum int) (int, error) {
	entries, err := os.ReadDir(queueDir)
	if err != nil {
		if os.IsNotExist(err) {
			return 0, nil
		}
		return 0, err
	}
	names := make([]string, 0, len(entries))
	for _, entry := range entries {
		if !entry.IsDir() && strings.HasSuffix(strings.ToLower(entry.Name()), ".json") {
			names = append(names, entry.Name())
		}
	}
	sort.Strings(names)
	if maximum <= 0 {
		maximum = 10
	}
	flushed := 0
	for _, name := range names {
		if flushed >= maximum {
			break
		}
		path := filepath.Join(queueDir, name)
		raw, readErr := os.ReadFile(path)
		if readErr != nil {
			continue
		}
		var payload HeartbeatPayload
		if json.Unmarshal(raw, &payload) != nil {
			_ = os.Rename(path, path+".invalid")
			continue
		}
		if payload.Delivery == nil {
			payload.Delivery = &DeliveryInfo{}
		}
		payload.Delivery.IsReplay = true
		if payload.Delivery.QueuedAt == "" {
			payload.Delivery.QueuedAt = time.Now().UTC().Format(time.RFC3339)
		}
		payload.Delivery.Attempt++
		_, status, sendErr := sendHeartbeat(cfg, payload)
		if sendErr != nil {
			return flushed, sendErr
		}
		if status < 200 || status >= 300 {
			if status == http.StatusBadRequest || status == http.StatusNotFound || status == http.StatusRequestEntityTooLarge || status == http.StatusUnprocessableEntity {
				_ = os.Rename(path, path+".rejected")
				continue
			}
			return flushed, fmt.Errorf("queued heartbeat returned HTTP %d", status)
		}
		if err := os.Remove(path); err != nil {
			return flushed, err
		}
		flushed++
	}
	return flushed, nil
}

func cleanupHeartbeatQueue(queueDir string, maximum int, maxAge time.Duration) {
	entries, err := os.ReadDir(queueDir)
	if err != nil {
		return
	}
	type queuedFile struct {
		path     string
		modified time.Time
	}
	files := make([]queuedFile, 0, len(entries))
	cutoff := time.Now().Add(-maxAge)
	for _, entry := range entries {
		if entry.IsDir() || !strings.HasSuffix(strings.ToLower(entry.Name()), ".json") {
			continue
		}
		info, infoErr := entry.Info()
		if infoErr != nil {
			continue
		}
		path := filepath.Join(queueDir, entry.Name())
		if maxAge > 0 && info.ModTime().Before(cutoff) {
			_ = os.Remove(path)
			continue
		}
		files = append(files, queuedFile{path: path, modified: info.ModTime()})
	}
	sort.Slice(files, func(i, j int) bool { return files[i].modified.Before(files[j].modified) })
	if maximum <= 0 {
		maximum = 100
	}
	for len(files) > maximum {
		_ = os.Remove(files[0].path)
		files = files[1:]
	}
}

func errorText(err error) string {
	if err == nil {
		return ""
	}
	return err.Error()
}

func collectWindowsUpdateInfo(timeout time.Duration) *WindowsUpdateInfo {
	if runtime.GOOS != "windows" {
		return nil
	}
	script := `$ErrorActionPreference='Stop'; [Console]::OutputEncoding=[System.Text.UTF8Encoding]::new($false); $OutputEncoding=[Console]::OutputEncoding;
function Convert-HotFixDate($value){
 if($null -eq $value){return $null}; $s=[string]$value; if([string]::IsNullOrWhiteSpace($s)){return $null};
 if($s -match '^[0-9A-Fa-f]{16}$'){try{$fileTime=[Convert]::ToInt64($s,16); if($fileTime -gt 0){return [datetime]::FromFileTimeUtc($fileTime)}}catch{}};
 $styles=[Globalization.DateTimeStyles]::AllowWhiteSpaces;
 foreach($culture in @([Globalization.CultureInfo]::CurrentCulture,[Globalization.CultureInfo]::GetCultureInfo('pl-PL'),[Globalization.CultureInfo]::GetCultureInfo('en-US'),[Globalization.CultureInfo]::InvariantCulture)){
  $parsed=[datetime]::MinValue; if([datetime]::TryParse($s,$culture,$styles,[ref]$parsed)){return $parsed}
 };
 foreach($format in @('yyyy-MM-dd','yyyy/MM/dd','dd.MM.yyyy','d.M.yyyy','MM/dd/yyyy','M/d/yyyy','dd-MM-yyyy','d-M-yyyy')){
  $parsed=[datetime]::MinValue; if([datetime]::TryParseExact($s,$format,[Globalization.CultureInfo]::InvariantCulture,$styles,[ref]$parsed)){return $parsed}
 };
 return $null
};
$result=[ordered]@{available=$false;collected_at=(Get-Date).ToUniversalTime().ToString('o');last_installed_update_at=$null;last_hotfix_id='';pending_updates_count=0;pending_critical_count=0;pending_security_count=0;restart_required=$false;service_status='';error=''};
try {
 $svc=Get-Service -Name wuauserv -ErrorAction SilentlyContinue; if($svc){$result.service_status=[string]$svc.Status};
 $hotfixes=@(Get-CimInstance -ClassName Win32_QuickFixEngineering -ErrorAction SilentlyContinue);
 $datedHotfixes=@(); foreach($hf in $hotfixes){$installed=Convert-HotFixDate $hf.InstalledOn; if($null -ne $installed){$datedHotfixes += [pscustomobject]@{HotFixID=[string]$hf.HotFixID;InstalledOn=$installed}}};
 $hotfix=$datedHotfixes | Sort-Object InstalledOn -Descending | Select-Object -First 1;
 if($hotfix){$result.last_hotfix_id=[string]$hotfix.HotFixID; $result.last_installed_update_at=$hotfix.InstalledOn.ToUniversalTime().ToString('o')};
 $session=New-Object -ComObject Microsoft.Update.Session; $searcher=$session.CreateUpdateSearcher(); $search=$searcher.Search("IsInstalled=0 and IsHidden=0"); $updates=@($search.Updates); $result.pending_updates_count=$updates.Count;
 foreach($u in $updates){$cats=@($u.Categories|ForEach-Object{$_.Name}); $categoryIds=@($u.Categories|ForEach-Object{$_.CategoryID.ToString().ToLowerInvariant()}); if(($cats -contains 'Critical Updates') -or ($categoryIds -contains 'e6cf1350-c01b-414d-a61f-263d14d133b4')){$result.pending_critical_count++}; if(($cats -contains 'Security Updates') -or ($cats -contains 'Definition Updates') -or ($categoryIds -contains '0fa1201d-4330-4fa8-8ae9-b877473b6441') -or ($categoryIds -contains 'e0789628-ce08-4437-be74-2495b842f43b')){$result.pending_security_count++}};
 $result.restart_required=[bool](Test-Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\RebootRequired'); $result.available=$true
} catch { $result.error=$_.Exception.Message };
$result | ConvertTo-Json -Depth 5 -Compress`
	return runPowerShellJSON[WindowsUpdateInfo](script, timeout+45*time.Second)
}

func collectDefenderStatus(timeout time.Duration) *DefenderStatus {
	if runtime.GOOS != "windows" {
		return nil
	}
	script := `$ErrorActionPreference='Stop'; [Console]::OutputEncoding=[System.Text.UTF8Encoding]::new($false); $OutputEncoding=[Console]::OutputEncoding;
function B($v){if($null -eq $v){return $null};return [bool]$v};
function I($v){if($null -eq $v){return $null};try{$n=[int64]$v}catch{return $null};if(($n -lt 0) -or ($n -eq 4294967295) -or ($n -gt [int]::MaxValue)){return $null};return [int]$n};
function D($v){if($null -eq $v){return $null};try{return ([datetime]$v).ToUniversalTime().ToString('o')}catch{return $null}};
function Decode-ProductState($value){
 try{$state=[uint32]$value}catch{return [ordered]@{product_state=0;state_hex='';enabled=$null;up_to_date=$null}};
 $hex=('{0:X6}' -f $state);
 try{
  $stateByte=[Convert]::ToInt32($hex.Substring(2,2),16);
  $signatureByte=[Convert]::ToInt32($hex.Substring(4,2),16);
  $enabled=($stateByte -eq 0x10 -or $stateByte -eq 0x11);
  $upToDate=($signatureByte -eq 0x00);
 }catch{$enabled=$null;$upToDate=$null};
 return [ordered]@{product_state=[int64]$state;state_hex=$hex;enabled=$enabled;up_to_date=$upToDate}
};
$r=[ordered]@{available=$false;collected_at=(Get-Date).ToUniversalTime().ToString('o');am_running_mode='';antivirus_enabled=$null;antimalware_service_enabled=$null;real_time_protection_enabled=$null;behavior_monitor_enabled=$null;ioav_protection_enabled=$null;nis_enabled=$null;tamper_protected=$null;signature_age_days=$null;signature_last_updated=$null;quick_scan_age_days=$null;quick_scan_end_time=$null;full_scan_age_days=$null;full_scan_end_time=$null;active_threat_count=0;antivirus_products=@();firewall_profiles=@();error=''};
try {
 $mp=Get-MpComputerStatus;
 $r.am_running_mode=[string]$mp.AMRunningMode;
 $r.antivirus_enabled=B $mp.AntivirusEnabled;
 $r.antimalware_service_enabled=B $mp.AMServiceEnabled;
 $r.real_time_protection_enabled=B $mp.RealTimeProtectionEnabled;
 $r.behavior_monitor_enabled=B $mp.BehaviorMonitorEnabled;
 $r.ioav_protection_enabled=B $mp.IoavProtectionEnabled;
 $r.nis_enabled=B $mp.NISEnabled;
 $r.tamper_protected=B $mp.IsTamperProtected;
 $r.signature_age_days=I $mp.AntivirusSignatureAge;
 $r.signature_last_updated=D $mp.AntivirusSignatureLastUpdated;
 $r.quick_scan_age_days=I $mp.QuickScanAge;
 $r.quick_scan_end_time=D $mp.QuickScanEndTime;
 $r.full_scan_age_days=I $mp.FullScanAge;
 $r.full_scan_end_time=D $mp.FullScanEndTime;
 $r.active_threat_count=@(Get-MpThreat -ErrorAction SilentlyContinue | Where-Object{$_.IsActive}).Count;
 try {
  $r.antivirus_products=@(
   Get-CimInstance -Namespace 'root/SecurityCenter2' -ClassName 'AntiVirusProduct' -ErrorAction Stop |
   ForEach-Object {
    $decoded=Decode-ProductState $_.productState;
    [ordered]@{
     display_name=[string]$_.displayName;
     product_state=$decoded.product_state;
     state_hex=$decoded.state_hex;
     enabled=$decoded.enabled;
     up_to_date=$decoded.up_to_date;
     path_to_signed_product_exe=[string]$_.pathToSignedProductExe
    }
   }
  )
 } catch {
  $r.antivirus_products=@()
 };
 $r.firewall_profiles=@(
  Get-NetFirewallProfile -ErrorAction SilentlyContinue |
  ForEach-Object{
   [ordered]@{
    name=[string]$_.Name;
    enabled=B $_.Enabled;
    default_inbound_action=[string]$_.DefaultInboundAction;
    default_outbound_action=[string]$_.DefaultOutboundAction
   }
  }
 );
 $r.available=$true
} catch {
 $r.error=$_.Exception.Message
};
$r | ConvertTo-Json -Depth 8 -Compress`
	return runPowerShellJSON[DefenderStatus](script, timeout+35*time.Second)
}

func runPowerShellJSON[T any](script string, timeout time.Duration) *T {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()
	cmd := exec.CommandContext(ctx, "powershell.exe", "-NoLogo", "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-Command", script)
	raw, err := cmd.CombinedOutput()
	if err != nil {
		return nil
	}
	raw = bytes.TrimSpace(stripBOM(raw))
	if len(raw) == 0 {
		return nil
	}
	var result T
	if json.Unmarshal(raw, &result) != nil {
		return nil
	}
	return &result
}

func collectSmartInfo(timeout time.Duration) *SmartInfo {
	if runtime.GOOS != "windows" {
		return nil
	}

	script := `$ErrorActionPreference='SilentlyContinue';
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false);
$OutputEncoding = [Console]::OutputEncoding;

function Convert-ToNullableDouble($value) {
    if ($null -eq $value -or [string]::IsNullOrWhiteSpace([string]$value)) {
        return $null
    }
    try {
        return [double]$value
    } catch {
        return $null
    }
}

function Convert-ToNullableInt64($value) {
    if ($null -eq $value -or [string]::IsNullOrWhiteSpace([string]$value)) {
        return $null
    }
    try {
        return [int64]$value
    } catch {
        return $null
    }
}

$results = @();
$physicalDisks = @(Get-PhysicalDisk -ErrorAction SilentlyContinue);

if ($physicalDisks.Count -gt 0) {
    foreach ($disk in $physicalDisks) {
        $reliability = $null;
        try {
            $reliability = Get-StorageReliabilityCounter -PhysicalDisk $disk -ErrorAction Stop;
        } catch {
            $reliability = $null;
        }

        $operational = @();
        if ($disk.OperationalStatus) {
            $operational = @($disk.OperationalStatus | ForEach-Object { [string]$_ });
        }

        $health = [string]$disk.HealthStatus;
        $predictFailure = $false;

        if ($health -and $health -notin @('Healthy', 'Unknown')) {
            $predictFailure = $true;
        }

        foreach ($state in $operational) {
            if ($state -match 'Predict|Failure|Error|Lost Communication|Degraded') {
                $predictFailure = $true;
            }
        }

        $temperature = $null;
        $maxTemperature = $null;
        $wear = $null;
        $powerOnHours = $null;
        $readErrors = $null;
        $writeErrors = $null;

        if ($reliability) {
            $temperature = Convert-ToNullableDouble $reliability.Temperature;
            $maxTemperature = Convert-ToNullableDouble $reliability.TemperatureMax;
            $wear = Convert-ToNullableDouble $reliability.Wear;
            $powerOnHours = Convert-ToNullableInt64 $reliability.PowerOnHours;
            $readErrors = Convert-ToNullableInt64 $reliability.ReadErrorsTotal;
            $writeErrors = Convert-ToNullableInt64 $reliability.WriteErrorsTotal;
        }

        $smartSupported = ($null -ne $reliability);
        $message = '';

        if ($predictFailure) {
            $message = 'Dysk zgłasza stan ostrzegawczy lub przewidywaną awarię.';
        } elseif (-not $smartSupported) {
            $message = 'Sterownik dysku nie udostępnia szczegółowych liczników SMART.';
        } elseif ($temperature -ne $null -and $temperature -ge 65) {
            $message = 'Temperatura dysku jest krytycznie wysoka.';
        } elseif ($wear -ne $null -and $wear -ge 95) {
            $message = 'Nośnik SSD jest blisko końca deklarowanej żywotności.';
        } elseif ($temperature -ne $null -and $temperature -ge 55) {
            $message = 'Temperatura dysku jest podwyższona.';
        } elseif ($wear -ne $null -and $wear -ge 80) {
            $message = 'Zużycie nośnika SSD jest wysokie.';
        } else {
            $message = 'Nie wykryto jednoznacznych problemów SMART.';
        }

        $results += [ordered]@{
            device_id=[string]$disk.DeviceId;
            friendly_name=[string]$disk.FriendlyName;
            model=[string]$disk.Model;
            media_type=[string]$disk.MediaType;
            bus_type=[string]$disk.BusType;
            size_bytes=Convert-ToNullableInt64 $disk.Size;
            health_status=$health;
            operational_status=$operational;
            temperature_c=$temperature;
            max_temperature_c=$maxTemperature;
            wear_percent_used=$wear;
            power_on_hours=$powerOnHours;
            read_errors_total=$readErrors;
            write_errors_total=$writeErrors;
            predict_failure=[bool]$predictFailure;
            smart_supported=[bool]$smartSupported;
            status_message=$message
        };
    }
} else {
    foreach ($disk in @(Get-CimInstance Win32_DiskDrive -ErrorAction SilentlyContinue)) {
        $status = [string]$disk.Status;
        $predictFailure = ($status -and $status -notin @('OK', 'Unknown'));

        $results += [ordered]@{
            device_id=[string]$disk.Index;
            friendly_name=[string]$disk.Caption;
            model=[string]$disk.Model;
            media_type=[string]$disk.MediaType;
            bus_type=[string]$disk.InterfaceType;
            size_bytes=Convert-ToNullableInt64 $disk.Size;
            health_status=if ($predictFailure) { 'Warning' } else { $status };
            operational_status=@();
            temperature_c=$null;
            max_temperature_c=$null;
            wear_percent_used=$null;
            power_on_hours=$null;
            read_errors_total=$null;
            write_errors_total=$null;
            predict_failure=[bool]$predictFailure;
            smart_supported=$false;
            status_message=if ($predictFailure) {
                'System Windows zgłasza problem z dyskiem.'
            } else {
                'Dostępne są tylko podstawowe dane o dysku.'
            }
        };
    }
}

[ordered]@{
    disks=$results;
    collected_at=(Get-Date).ToUniversalTime().ToString('o')
} | ConvertTo-Json -Depth 7 -Compress`

	ctx, cancel := context.WithTimeout(context.Background(), timeout+18*time.Second)
	defer cancel()

	cmd := exec.CommandContext(
		ctx,
		"powershell.exe",
		"-NoLogo",
		"-NoProfile",
		"-NonInteractive",
		"-ExecutionPolicy",
		"Bypass",
		"-Command",
		script,
	)

	raw, err := cmd.CombinedOutput()
	if err != nil {
		return nil
	}

	raw = bytes.TrimSpace(stripBOM(raw))
	if len(raw) == 0 {
		return nil
	}

	var info SmartInfo
	if err := json.Unmarshal(raw, &info); err != nil {
		return nil
	}

	return &info
}

func collectSystemInfo(timeout time.Duration) *SystemInfo {
	if runtime.GOOS != "windows" {
		return nil
	}

	// Pełny inwentarz systemu jest pobierany rzadko. Bieżące CPU, RAM i uptime
	// są uzupełniane później bezpośrednio przez WinAPI.
	script := `$ErrorActionPreference='SilentlyContinue';
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false);
$OutputEncoding = [Console]::OutputEncoding;

$cpuItems = @(Get-CimInstance Win32_Processor | Select-Object Name,NumberOfCores,NumberOfLogicalProcessors);
$cpuModel = ($cpuItems | Select-Object -First 1 -ExpandProperty Name);
$cpuCores = ($cpuItems | Measure-Object -Property NumberOfCores -Sum).Sum;
$cpuLogical = ($cpuItems | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum;

$os = Get-CimInstance Win32_OperatingSystem | Select-Object -First 1 Caption,Version,LastBootUpTime;
$bootTime = $null;
if ($os -and $os.LastBootUpTime) {
    try {
        $bootDate = [Management.ManagementDateTimeConverter]::ToDateTime($os.LastBootUpTime.ToString());
        $bootTime = $bootDate.ToUniversalTime().ToString('o');
    } catch {
        $bootTime = $null;
    }
}

$disks = @();
foreach ($disk in @(Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" | Select-Object DeviceID,VolumeName,FileSystem,Size,FreeSpace)) {
    $total = if ($disk.Size -ne $null) { [int64]$disk.Size } else { $null };
    $free = if ($disk.FreeSpace -ne $null) { [int64]$disk.FreeSpace } else { $null };
    $used = $null;
    $usage = $null;
    if ($total -ne $null -and $free -ne $null) {
        $used = [int64]($total - $free);
        if ($total -gt 0) { $usage = [math]::Round(($used / $total) * 100, 1) }
    }
    $disks += [ordered]@{
        name=[string]$disk.DeviceID;
        label=[string]$disk.VolumeName;
        filesystem=[string]$disk.FileSystem;
        total_bytes=$total;
        used_bytes=$used;
        free_bytes=$free;
        usage_percent=$usage
    };
}

[ordered]@{
    cpu=[ordered]@{
        usage_percent=$null;
        logical_processors=if ($cpuLogical -ne $null) { [int]$cpuLogical } else { $null };
        cores=if ($cpuCores -ne $null) { [int]$cpuCores } else { $null };
        model=[string]$cpuModel
    };
    memory=$null;
    disks=$disks;
    uptime_seconds=$null;
    boot_time=$bootTime;
    computer_name=[string]$env:COMPUTERNAME;
    os_caption=if ($os) { [string]$os.Caption } else { '' };
    os_version=if ($os) { [string]$os.Version } else { '' }
} | ConvertTo-Json -Depth 6 -Compress`

	ctx, cancel := context.WithTimeout(context.Background(), timeout+8*time.Second)
	defer cancel()

	cmd := exec.CommandContext(
		ctx,
		"powershell.exe",
		"-NoLogo",
		"-NoProfile",
		"-NonInteractive",
		"-ExecutionPolicy",
		"Bypass",
		"-Command",
		script,
	)

	raw, err := cmd.CombinedOutput()
	if err != nil {
		return nil
	}

	raw = bytes.TrimSpace(stripBOM(raw))
	if len(raw) == 0 {
		return nil
	}

	var info SystemInfo
	if err := json.Unmarshal(raw, &info); err != nil {
		return nil
	}

	return &info
}

func collectWindowsServices(configs []WindowsServiceConfig, timeout time.Duration) []WindowsServiceStatus {
	if runtime.GOOS != "windows" || len(configs) == 0 {
		return nil
	}

	configJSON, err := json.Marshal(configs)
	if err != nil {
		return nil
	}

	encoded := bytes.ReplaceAll(configJSON, []byte("'"), []byte("''"))
	script := fmt.Sprintf(`$ErrorActionPreference='SilentlyContinue';
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false);
$OutputEncoding = [Console]::OutputEncoding;
$configs = ConvertFrom-Json '%s';
$result = @();
foreach ($cfg in @($configs)) {
    $name = [string]$cfg.name;
    $label = if ($cfg.label) { [string]$cfg.label } else { $name };
    $expected = if ($cfg.expected_status) { [string]$cfg.expected_status } else { 'Running' };
    $alert = [bool]$cfg.alert;
    $service = Get-CimInstance Win32_Service -Filter ("Name='" + ($name -replace "'","''") + "'") | Select-Object -First 1;
    if (-not $service) {
        $result += [ordered]@{
            name=$name; label=$label; exists=$false; status='Missing';
            expected_status=$expected; alert=$alert; healthy=(-not $alert);
            error='Usługa nie została znaleziona.'
        };
        continue;
    }
    $status = [string]$service.State;
    $startType = [string]$service.StartMode;
    $healthy = ($status -eq $expected);
    $result += [ordered]@{
        name=$name; label=$label; display_name=[string]$service.DisplayName;
        exists=$true; status=$status; start_type=$startType;
        expected_status=$expected; alert=$alert; healthy=$healthy
    };
}
$result | ConvertTo-Json -Depth 5 -Compress`, string(encoded))

	ctx, cancel := context.WithTimeout(context.Background(), timeout+8*time.Second)
	defer cancel()

	cmd := exec.CommandContext(
		ctx,
		"powershell.exe",
		"-NoLogo",
		"-NoProfile",
		"-NonInteractive",
		"-ExecutionPolicy",
		"Bypass",
		"-Command",
		script,
	)

	raw, err := cmd.CombinedOutput()
	if err != nil {
		return nil
	}

	raw = bytes.TrimSpace(stripBOM(raw))
	if len(raw) == 0 {
		return nil
	}

	var statuses []WindowsServiceStatus
	if raw[0] == '{' {
		var one WindowsServiceStatus
		if err := json.Unmarshal(raw, &one); err != nil {
			return nil
		}
		statuses = []WindowsServiceStatus{one}
	} else if err := json.Unmarshal(raw, &statuses); err != nil {
		return nil
	}

	return statuses
}

func collectNetworkInfo(timeout time.Duration) *NetworkInfo {
	if runtime.GOOS != "windows" {
		return nil
	}

	// PowerShell 5.1 can return UTF-16 or stop serializing the whole object when
	// a single adapter property is unavailable. The script below forces UTF-8
	// and treats every optional property independently.
	script := `$ErrorActionPreference='SilentlyContinue';
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false);
$OutputEncoding = [Console]::OutputEncoding;
$route = Get-NetRoute -AddressFamily IPv4 -DestinationPrefix '0.0.0.0/0' |
    Where-Object { $_.NextHop -and $_.NextHop -ne '0.0.0.0' } |
    Sort-Object RouteMetric |
    Select-Object -First 1;
if (-not $route) { Write-Output '{}'; exit 0 }
$idx = [int]$route.InterfaceIndex;
$adapter = Get-NetAdapter -InterfaceIndex $idx | Select-Object -First 1;
$ipcfg = Get-NetIPConfiguration -InterfaceIndex $idx;
$ipif = Get-NetIPInterface -InterfaceIndex $idx -AddressFamily IPv4 | Select-Object -First 1;
$stats = if ($adapter) { Get-NetAdapterStatistics -Name $adapter.Name } else { $null };
$driver = $adapter;
$ipv4 = $ipcfg.IPv4Address | Select-Object -First 1;
$ipv6 = @($ipcfg.IPv6Address | ForEach-Object { $_.IPAddress } | Where-Object { $_ });
$dns = @($ipcfg.DNSServer.ServerAddresses | Where-Object { $_ });
$name = if ($adapter) { [string]$adapter.Name } else { [string]$ipcfg.InterfaceAlias };
$description = if ($adapter) { [string]$adapter.InterfaceDescription } else { '' };
$type = 'other';
if ($name -match 'Wi-?Fi|Wireless|WLAN' -or $description -match 'Wi-?Fi|Wireless|802\.11') { $type='wifi' }
elseif ($name -match 'VPN|TAP|Tunnel' -or $description -match 'VPN|TAP|Tunnel') { $type='vpn' }
elseif ($adapter) { $type='ethernet' };
$o = [ordered]@{};
$o.connection_type = $type;
$o.interface_alias = $name;
$o.interface_description = $description;
$o.interface_index = $idx;
$o.adapter_status = if ($adapter) { [string]$adapter.Status } else { 'Up' };
$o.link_speed = if ($adapter) { [string]$adapter.LinkSpeed } else { $null };
$o.mac_address = if ($adapter) { [string]$adapter.MacAddress } else { $null };
$o.mtu = if ($ipif -and $null -ne $ipif.NlMtuBytes) { [int]$ipif.NlMtuBytes } else { $null };
$o.ipv4_address = if ($ipv4) { [string]$ipv4.IPAddress } else { $null };
$o.ipv4_prefix_length = if ($ipv4 -and $null -ne $ipv4.PrefixLength) { [int]$ipv4.PrefixLength } else { $null };
$o.ipv6_addresses = $ipv6;
$o.default_gateway = [string]$route.NextHop;
$o.dns_servers = $dns;
$o.dhcp_enabled = if ($ipif) { ([string]$ipif.Dhcp -eq 'Enabled') } else { $null };
$o.driver_version = if ($driver) { [string]$driver.DriverVersion } else { $null };
$o.driver_date = if ($driver -and $driver.DriverDate) { try { ([datetime]$driver.DriverDate).ToString('yyyy-MM-dd') } catch { $null } } else { $null };
$o.manufacturer = if ($adapter) { [string]$adapter.DriverProvider } else { $null };
$o.received_bytes = if ($stats -and $null -ne $stats.ReceivedBytes) { [long]$stats.ReceivedBytes } else { $null };
$o.sent_bytes = if ($stats -and $null -ne $stats.SentBytes) { [long]$stats.SentBytes } else { $null };
$o.received_errors = if ($stats -and $null -ne $stats.ReceivedPacketErrors) { [long]$stats.ReceivedPacketErrors } else { $null };
$o.sent_errors = if ($stats -and $null -ne $stats.OutboundPacketErrors) { [long]$stats.OutboundPacketErrors } else { $null };
$o.received_discards = if ($stats -and $null -ne $stats.ReceivedDiscardedPackets) { [long]$stats.ReceivedDiscardedPackets } else { $null };
$o.sent_discards = if ($stats -and $null -ne $stats.OutboundDiscardedPackets) { [long]$stats.OutboundDiscardedPackets } else { $null };
if ($type -eq 'wifi') {
    $raw = (netsh wlan show interfaces) -join [Environment]::NewLine;
    $wifi = [ordered]@{};
    foreach ($line in ($raw -split [Environment]::NewLine)) {
        if ($line -match '^\s*(SSID|Nazwa SSID)\s*:\s*(.+)$' -and $line -notmatch 'BSSID') { $wifi.ssid=$matches[2].Trim() }
        elseif ($line -match '^\s*BSSID\s*:\s*(.+)$') { $wifi.bssid=$matches[1].Trim() }
        elseif ($line -match '^\s*(Signal|Sygnał)\s*:\s*(\d+)%') { $wifi.signal_percent=[int]$matches[2] }
        elseif ($line -match '^\s*(Radio type|Typ radia)\s*:\s*(.+)$') { $wifi.radio_type=$matches[2].Trim() }
        elseif ($line -match '^\s*(Channel|Kanał)\s*:\s*(\d+)') { $wifi.channel=[int]$matches[2] }
        elseif ($line -match '^\s*(Receive rate \(Mbps\)|Szybkość odbierania \(Mb/s\))\s*:\s*([\d\.,]+)') { $wifi.receive_rate_mbps=[double]($matches[2]-replace ',','.') }
        elseif ($line -match '^\s*(Transmit rate \(Mbps\)|Szybkość transmisji \(Mb/s\))\s*:\s*([\d\.,]+)') { $wifi.transmit_rate_mbps=[double]($matches[2]-replace ',','.') }
    }
    $o.wifi = $wifi;
}
$o | ConvertTo-Json -Depth 5 -Compress`

	ctx, cancel := context.WithTimeout(context.Background(), timeout+5*time.Second)
	defer cancel()
	cmd := exec.CommandContext(ctx, "powershell.exe", "-NoLogo", "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-Command", script)
	raw, err := cmd.CombinedOutput()
	if err != nil {
		return nil
	}
	raw = bytes.TrimSpace(stripBOM(raw))
	if len(raw) == 0 {
		return nil
	}
	var info NetworkInfo
	if err := json.Unmarshal(raw, &info); err != nil {
		return nil
	}
	if info.InterfaceAlias == "" && info.IPv4Address == "" && info.DefaultGateway == "" {
		return nil
	}
	return &info
}

func testDefaultGateway(info *NetworkInfo, timeout time.Duration) *bool {
	if runtime.GOOS != "windows" || info == nil || net.ParseIP(info.DefaultGateway) == nil {
		return nil
	}
	cmd := exec.Command("ping.exe", "-n", "1", "-w", fmt.Sprintf("%d", timeout.Milliseconds()), info.DefaultGateway)
	ok := cmd.Run() == nil
	return &ok
}
func determineDiagnosticStatus(g *bool, d, i bool, m *bool) string {
	if g != nil && !*g {
		return "gateway_problem"
	}
	if !d {
		return "dns_problem"
	}
	if !i {
		return "internet_problem"
	}
	if m != nil && !*m {
		return "monitoring_server_problem"
	}
	return "online"
}
func testDNS(host string, timeout time.Duration) (bool, *int64) {
	r := net.Resolver{}
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()
	start := time.Now()
	_, err := r.LookupHost(ctx, host)
	e := time.Since(start).Milliseconds()
	return err == nil, &e
}
func newHTTPClient(timeout time.Duration) *http.Client {
	return &http.Client{
		Timeout: timeout,
		Transport: &http.Transport{
			TLSClientConfig: &tls.Config{MinVersion: tls.VersionTLS12},
			Proxy:           http.ProxyFromEnvironment,
		},
	}
}

func newAPIClient(timeout time.Duration) *http.Client {
	client := newHTTPClient(timeout)
	client.CheckRedirect = func(req *http.Request, via []*http.Request) error {
		return http.ErrUseLastResponse
	}
	return client
}
func testMonitoringServer(url string, timeout time.Duration) *bool {
	if strings.TrimSpace(url) == "" {
		return nil
	}
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		v := false
		return &v
	}
	req.Header.Set("User-Agent", "PlacowkaOnlineAgent/"+builtInVersion)
	resp, err := newAPIClient(timeout).Do(req)
	if err != nil {
		v := false
		return &v
	}
	_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 64*1024))
	_ = resp.Body.Close()
	v := resp.StatusCode >= 200 && resp.StatusCode < 500
	return &v
}
func testInternet(urls []string, timeout time.Duration) InternetResult {
	client := newHTTPClient(timeout)
	type indexedResult struct {
		index  int
		detail TestDetail
	}

	results := make(chan indexedResult, len(urls))
	var wg sync.WaitGroup

	for index, rawURL := range urls {
		index := index
		rawURL := rawURL
		wg.Add(1)
		go func() {
			defer wg.Done()
			start := time.Now()
			detail := TestDetail{URL: rawURL}
			req, err := http.NewRequest(http.MethodGet, rawURL, nil)
			if err != nil {
				detail.Error = err.Error()
				results <- indexedResult{index, detail}
				return
			}
			req.Header.Set("User-Agent", "PlacowkaOnlineAgent/"+builtInVersion)
			resp, err := client.Do(req)
			detail.LatencyMS = time.Since(start).Milliseconds()
			if err != nil {
				detail.Error = err.Error()
				results <- indexedResult{index, detail}
				return
			}
			_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 64*1024))
			_ = resp.Body.Close()
			if resp.StatusCode >= 200 && resp.StatusCode < 400 {
				detail.OK = true
			} else {
				detail.Error = fmt.Sprintf("HTTP %d", resp.StatusCode)
			}
			results <- indexedResult{index, detail}
		}()
	}

	wg.Wait()
	close(results)

	details := make([]TestDetail, len(urls))
	var latencies []int64
	success := 0
	for result := range results {
		details[result.index] = result.detail
		if result.detail.OK {
			success++
			latencies = append(latencies, result.detail.LatencyMS)
		}
	}

	var average *int64
	if len(latencies) > 0 {
		var sum int64
		for _, latency := range latencies {
			sum += latency
		}
		value := sum / int64(len(latencies))
		average = &value
	}

	required := 2
	if len(urls) < 2 {
		required = 1
	}

	return InternetResult{
		InternetOK:   success >= required,
		AvgLatencyMS: average,
		Details:      details,
	}
}

func collectConnectivity(
	cfg Config,
	networkInfo *NetworkInfo,
) (*bool, bool, *int64, InternetResult, *bool) {
	var gatewayOK *bool
	var dnsOK bool
	var dnsLatency *int64
	var internet InternetResult
	var monitoringOK *bool

	var wg sync.WaitGroup
	wg.Add(4)

	go func() {
		defer wg.Done()
		gatewayOK = testDefaultGateway(
			networkInfo,
			time.Duration(cfg.TimeoutSeconds)*time.Second,
		)
	}()
	go func() {
		defer wg.Done()
		dnsOK, dnsLatency = testDNS(
			cfg.DNSTestHost,
			time.Duration(cfg.TimeoutSeconds)*time.Second,
		)
	}()
	go func() {
		defer wg.Done()
		internet = testInternet(
			cfg.TestURLs,
			time.Duration(cfg.TimeoutSeconds)*time.Second,
		)
	}()
	go func() {
		defer wg.Done()
		monitoringOK = testMonitoringServer(
			cfg.MonitoringURL,
			time.Duration(cfg.TimeoutSeconds)*time.Second,
		)
	}()

	wg.Wait()
	return gatewayOK, dnsOK, dnsLatency, internet, monitoringOK
}

func performAgentSelfCheck(baseDir, logDir, statePath, mode string) AgentHealth {
	now := time.Now().UTC().Format(time.RFC3339)
	health := AgentHealth{
		Status:               "unknown",
		ConfigValid:          true,
		StateFileWritable:    testWritableDirectory(filepath.Dir(statePath)),
		LogDirectoryWritable: testWritableDirectory(logDir),
		SelfCheckAt:          now,
	}

	if mode == "service" {
		taskPath := filepath.Join(os.Getenv("WINDIR"), "System32", "Tasks", "PlacowkaOnlineAgent")
		present := false
		if strings.TrimSpace(os.Getenv("WINDIR")) != "" {
			if info, err := os.Stat(taskPath); err == nil && !info.IsDir() {
				present = true
			}
		}
		health.TaskPresent = &present
	}

	return health
}

func testWritableDirectory(dir string) bool {
	if strings.TrimSpace(dir) == "" {
		return false
	}
	if err := os.MkdirAll(dir, 0755); err != nil {
		return false
	}
	file, err := os.CreateTemp(dir, ".agent-selfcheck-*")
	if err != nil {
		return false
	}
	name := file.Name()
	_, writeErr := file.Write([]byte("ok"))
	closeErr := file.Close()
	removeErr := os.Remove(name)
	return writeErr == nil && closeErr == nil && removeErr == nil
}

func buildAgentHealth(cfg Config, state AgentState, started, now time.Time) AgentHealth {
	health := AgentHealth{Status: "unknown"}
	if state.AgentHealth != nil {
		health = *state.AgentHealth
	}

	health.Profile = cfg.PerformanceProfile
	health.RunMode = runMode
	health.CycleDurationMS = time.Since(started).Milliseconds()
	health.LastSystemAt = state.LastSystemAt
	health.LastNetworkAt = state.LastNetworkAt
	health.LastServicesAt = state.LastServicesAt
	health.LastSmartAt = state.LastSmartAt
	health.ConsecutiveFailures = state.ConsecutiveFailures
	health.LastFailureAt = state.LastFailureAt
	health.LastFailureReason = truncateText(state.LastFailureReason, 300)
	health.ClockSkewSeconds = state.LastClockSkewSeconds

	missing := make([]string, 0, 4)
	points := 0
	if state.SystemInfo != nil && strings.TrimSpace(state.LastSystemAt) != "" {
		points += 30
	} else {
		missing = append(missing, "system")
	}
	if state.NetworkInfo != nil && strings.TrimSpace(state.LastNetworkAt) != "" {
		points += 20
	} else {
		missing = append(missing, "network")
	}
	if len(cfg.WindowsServices) == 0 || strings.TrimSpace(state.LastServicesAt) != "" {
		points += 20
	} else {
		missing = append(missing, "services")
	}
	if state.SmartInfo != nil && strings.TrimSpace(state.LastSmartAt) != "" {
		points += 30
	} else {
		missing = append(missing, "smart")
	}
	health.TelemetryCompletenessPercent = points
	health.MissingModules = missing

	health.Status = "healthy"
	if !health.ConfigValid || !health.StateFileWritable || !health.LogDirectoryWritable {
		health.Status = "critical"
	} else if health.TaskPresent != nil && !*health.TaskPresent && runMode == "service" {
		health.Status = "critical"
	} else if state.ConsecutiveFailures >= 3 {
		health.Status = "critical"
	} else if runMode == "service" && health.CycleDurationMS > 120000 {
		health.Status = "critical"
	} else if len(missing) > 0 || state.ConsecutiveFailures > 0 {
		health.Status = "warning"
	} else if runMode == "service" && health.CycleDurationMS > 30000 {
		health.Status = "warning"
	} else if state.LastClockSkewSeconds != nil && absInt64(*state.LastClockSkewSeconds) > 300 {
		health.Status = "warning"
	}

	if health.SelfCheckAt == "" {
		health.SelfCheckAt = now.Format(time.RFC3339)
	}
	return health
}

func recordHeartbeatFailure(state *AgentState, now time.Time, reason string) {
	state.ConsecutiveFailures++
	state.LastFailureAt = now.Format(time.RFC3339)
	state.LastFailureReason = truncateText(reason, 300)
}

func responseClockSkew(body string, receivedAt time.Time) (int64, bool) {
	var response struct {
		ServerTime string `json:"server_time"`
	}
	if json.Unmarshal([]byte(body), &response) != nil || strings.TrimSpace(response.ServerTime) == "" {
		return 0, false
	}
	serverTime, err := time.Parse(time.RFC3339, response.ServerTime)
	if err != nil {
		return 0, false
	}
	return int64(serverTime.Sub(receivedAt).Round(time.Second) / time.Second), true
}

func truncateText(value string, maximum int) string {
	value = strings.TrimSpace(value)
	if maximum <= 0 || len(value) <= maximum {
		return value
	}
	return value[:maximum]
}

func absInt64(value int64) int64 {
	if value < 0 {
		return -value
	}
	return value
}

func normalizePerformanceConfig(cfg *Config) {
	if cfg.TimeoutSeconds <= 0 {
		cfg.TimeoutSeconds = 8
	}
	if cfg.AgentVersion == "" {
		cfg.AgentVersion = builtInVersion
	}
	if cfg.PerformanceProfile == "" {
		cfg.PerformanceProfile = "low_impact"
	}
	cfg.SystemIntervalMinutes = clampInt(cfg.SystemIntervalMinutes, 30, 5, 120)
	cfg.NetworkIntervalMinutes = clampInt(cfg.NetworkIntervalMinutes, 15, 3, 120)
	cfg.ServicesIntervalMinutes = clampInt(cfg.ServicesIntervalMinutes, 5, 2, 60)
	cfg.SmartIntervalMinutes = clampInt(cfg.SmartIntervalMinutes, 60, 15, 1440)
	cfg.SelfCheckIntervalMinutes = clampInt(cfg.SelfCheckIntervalMinutes, 30, 10, 240)
	cfg.WindowsUpdateIntervalMinutes = clampInt(cfg.WindowsUpdateIntervalMinutes, 720, 60, 10080)
	cfg.DefenderIntervalMinutes = clampInt(cfg.DefenderIntervalMinutes, 360, 30, 1440)
	cfg.OfflineQueueMaxItems = clampInt(cfg.OfflineQueueMaxItems, 100, 10, 1000)
	cfg.OfflineQueueMaxAgeDays = clampInt(cfg.OfflineQueueMaxAgeDays, 7, 1, 30)
	cfg.OfflineQueueFlushPerCycle = clampInt(cfg.OfflineQueueFlushPerCycle, 10, 1, 100)
	cfg.StartupJitterSeconds = clampInt(cfg.StartupJitterSeconds, 15, 0, 30)
}

func clampInt(value, fallback, minimum, maximum int) int {
	if value <= 0 {
		value = fallback
	}
	if value < minimum {
		return minimum
	}
	if value > maximum {
		return maximum
	}
	return value
}

func deterministicJitter(cfg Config, maximumSeconds int) time.Duration {
	if maximumSeconds <= 0 {
		return 0
	}
	h := fnv.New32a()
	_, _ = h.Write([]byte(cfg.LocationCode + "|" + cfg.DeviceName + "|" + cfg.Token))
	seconds := int(h.Sum32() % uint32(maximumSeconds+1))
	return time.Duration(seconds) * time.Second
}

func loadState(path string) AgentState {
	raw, err := os.ReadFile(path)
	if err != nil {
		return AgentState{}
	}
	var state AgentState
	if json.Unmarshal(bytes.TrimSpace(stripBOM(raw)), &state) != nil {
		return AgentState{}
	}
	return state
}

func saveState(path string, state AgentState) error {
	raw, err := json.MarshalIndent(state, "", "  ")
	if err != nil {
		return err
	}
	temporary := path + ".tmp"
	if err := os.WriteFile(temporary, raw, 0600); err != nil {
		return err
	}
	_ = os.Remove(path)
	return os.Rename(temporary, path)
}

func isDue(last string, interval time.Duration, now time.Time) bool {
	if strings.TrimSpace(last) == "" {
		return true
	}
	parsed, err := time.Parse(time.RFC3339, last)
	if err != nil {
		return true
	}
	return now.Sub(parsed) >= interval
}

func elapsedSince(value string, now time.Time) time.Duration {
	parsed, err := time.Parse(time.RFC3339, value)
	if err != nil {
		return 0
	}
	return now.Sub(parsed)
}

func mergeSystemInfo(base *SystemInfo, dynamic *SystemInfo) *SystemInfo {
	if base == nil {
		base = &SystemInfo{}
	}
	if dynamic == nil {
		return base
	}

	if base.CPU == nil {
		base.CPU = &CPUInfo{}
	}
	if dynamic.CPU != nil {
		base.CPU.UsagePercent = dynamic.CPU.UsagePercent
	}
	if dynamic.Memory != nil {
		base.Memory = dynamic.Memory
	}
	if dynamic.UptimeSeconds != nil {
		base.UptimeSeconds = dynamic.UptimeSeconds
	}
	return base
}

type windowsFiletime struct {
	LowDateTime  uint32
	HighDateTime uint32
}

type memoryStatusEx struct {
	Length               uint32
	MemoryLoad           uint32
	TotalPhys            uint64
	AvailPhys            uint64
	TotalPageFile        uint64
	AvailPageFile        uint64
	TotalVirtual         uint64
	AvailVirtual         uint64
	AvailExtendedVirtual uint64
}

func collectLightweightSystemInfo() *SystemInfo {
	if runtime.GOOS != "windows" {
		return nil
	}

	info := &SystemInfo{}
	if usage, ok := sampleCPUUsage(250 * time.Millisecond); ok {
		info.CPU = &CPUInfo{UsagePercent: &usage}
	}
	if memory := readMemoryInfo(); memory != nil {
		info.Memory = memory
	}
	if uptime, ok := readUptimeSeconds(); ok {
		info.UptimeSeconds = &uptime
	}
	return info
}

func setBelowNormalPriority() {
	if runtime.GOOS != "windows" {
		return
	}
	kernel32 := syscall.NewLazyDLL("kernel32.dll")
	getCurrentProcess := kernel32.NewProc("GetCurrentProcess")
	setPriorityClass := kernel32.NewProc("SetPriorityClass")
	handle, _, _ := getCurrentProcess.Call()
	const belowNormalPriorityClass = 0x00004000
	_, _, _ = setPriorityClass.Call(handle, uintptr(belowNormalPriorityClass))
}

func sampleCPUUsage(sample time.Duration) (float64, bool) {
	idle1, kernel1, user1, ok := readSystemTimes()
	if !ok {
		return 0, false
	}
	time.Sleep(sample)
	idle2, kernel2, user2, ok := readSystemTimes()
	if !ok {
		return 0, false
	}

	idle := idle2 - idle1
	kernel := kernel2 - kernel1
	user := user2 - user1
	total := kernel + user
	if total == 0 || total < idle {
		return 0, false
	}
	usage := (float64(total-idle) / float64(total)) * 100
	if usage < 0 {
		usage = 0
	}
	if usage > 100 {
		usage = 100
	}
	return float64(int(usage*10+0.5)) / 10, true
}

func readSystemTimes() (uint64, uint64, uint64, bool) {
	kernel32 := syscall.NewLazyDLL("kernel32.dll")
	proc := kernel32.NewProc("GetSystemTimes")
	var idle windowsFiletime
	var kernel windowsFiletime
	var user windowsFiletime
	result, _, _ := proc.Call(
		uintptr(unsafe.Pointer(&idle)),
		uintptr(unsafe.Pointer(&kernel)),
		uintptr(unsafe.Pointer(&user)),
	)
	if result == 0 {
		return 0, 0, 0, false
	}
	return filetimeValue(idle), filetimeValue(kernel), filetimeValue(user), true
}

func filetimeValue(value windowsFiletime) uint64 {
	return uint64(value.HighDateTime)<<32 | uint64(value.LowDateTime)
}

func readMemoryInfo() *MemoryInfo {
	kernel32 := syscall.NewLazyDLL("kernel32.dll")
	proc := kernel32.NewProc("GlobalMemoryStatusEx")
	status := memoryStatusEx{Length: uint32(unsafe.Sizeof(memoryStatusEx{}))}
	result, _, _ := proc.Call(uintptr(unsafe.Pointer(&status)))
	if result == 0 || status.TotalPhys == 0 {
		return nil
	}
	total := int64(status.TotalPhys)
	free := int64(status.AvailPhys)
	used := total - free
	usage := float64(status.MemoryLoad)
	return &MemoryInfo{
		TotalBytes:   &total,
		UsedBytes:    &used,
		FreeBytes:    &free,
		UsagePercent: &usage,
	}
}

func readUptimeSeconds() (int64, bool) {
	kernel32 := syscall.NewLazyDLL("kernel32.dll")
	proc := kernel32.NewProc("GetTickCount64")
	milliseconds, _, _ := proc.Call()
	if milliseconds == 0 {
		return 0, false
	}
	return int64(milliseconds / 1000), true
}

func executableDir() (string, error) {
	exe, err := os.Executable()
	if err != nil {
		return "", err
	}
	return filepath.Dir(exe), nil
}
func loadConfig(path string) (Config, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return Config{}, fmt.Errorf("cannot read %s: %w", path, err)
	}
	raw = bytes.TrimSpace(stripBOM(raw))
	var cfg Config
	if err := json.Unmarshal(raw, &cfg); err != nil {
		return Config{}, fmt.Errorf("invalid JSON in config.json: %w", err)
	}
	return cfg, nil
}
func stripBOM(raw []byte) []byte {
	if len(raw) >= 3 && raw[0] == 0xEF && raw[1] == 0xBB && raw[2] == 0xBF {
		return raw[3:]
	}
	return raw
}
func validateConfig(c Config) error {
	var missing []string
	if strings.TrimSpace(c.APIURL) == "" {
		missing = append(missing, "api_url")
	}
	if strings.TrimSpace(c.Token) == "" || strings.Contains(c.Token, "WSTAW") || len(c.Token) < 32 || len(c.Token) > 128 {
		missing = append(missing, "token")
	}
	if len(c.TestURLs) == 0 || len(c.TestURLs) > 5 {
		missing = append(missing, "test_urls")
	}
	if strings.TrimSpace(c.DNSTestHost) == "" {
		missing = append(missing, "dns_test_host")
	}
	if len(missing) > 0 {
		return fmt.Errorf("missing or invalid fields: %s", strings.Join(missing, ", "))
	}

	if err := validateHTTPSURL("api_url", c.APIURL); err != nil {
		return err
	}
	if strings.TrimSpace(c.MonitoringURL) != "" {
		if err := validateHTTPSURL("monitoring_url", c.MonitoringURL); err != nil {
			return err
		}
	}
	for _, testURL := range c.TestURLs {
		if err := validateHTTPSURL("test_urls", testURL); err != nil {
			return err
		}
	}

	return nil
}

func validateHTTPSURL(field, raw string) error {
	parsed, err := url.Parse(strings.TrimSpace(raw))
	if err != nil || parsed.Scheme != "https" || parsed.Host == "" || parsed.User != nil {
		return fmt.Errorf("%s must be a valid HTTPS URL without embedded credentials", field)
	}
	return nil
}

func sendHeartbeat(c Config, p HeartbeatPayload) (string, int, error) {
	data, err := json.Marshal(p)
	if err != nil {
		return "", 0, err
	}
	req, err := http.NewRequest(http.MethodPost, c.APIURL, bytes.NewReader(data))
	if err != nil {
		return "", 0, err
	}
	req.Header.Set("Authorization", "Bearer "+c.Token)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "PlacowkaOnlineAgent/"+builtInVersion)
	resp, err := newAPIClient(time.Duration(c.TimeoutSeconds) * time.Second).Do(req)
	if err != nil {
		return "", 0, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1024*1024))
	return string(raw), resp.StatusCode, nil
}
func cleanupOldLogs(dir string, maxAge time.Duration) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return
	}
	cutoff := time.Now().Add(-maxAge)
	for _, entry := range entries {
		if entry.IsDir() || !strings.HasPrefix(entry.Name(), "agent-") || !strings.HasSuffix(entry.Name(), ".log") {
			continue
		}
		info, err := entry.Info()
		if err == nil && info.ModTime().Before(cutoff) {
			_ = os.Remove(filepath.Join(dir, entry.Name()))
		}
	}
}

func writeLog(dir, level, msg string, data interface{}) {
	_ = os.MkdirAll(dir, 0755)
	f, err := os.OpenFile(filepath.Join(dir, "agent-"+time.Now().Format("2006-01-02")+".log"), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0644)
	if err != nil {
		return
	}
	defer f.Close()
	raw, _ := json.Marshal(LogEntry{time.Now().Format(time.RFC3339), level, msg, data})
	_, _ = f.Write(append(raw, '\n'))
}
func prettyJSON(s string) string {
	var v interface{}
	if json.Unmarshal([]byte(s), &v) != nil {
		return s
	}
	b, _ := json.MarshalIndent(v, "", "  ")
	return string(b)
}
func friendlyError(err error) string {
	msg := err.Error()
	for _, r := range [][2]string{{"cannot read", "nie moge odczytac"}, {"invalid JSON", "niepoprawny JSON"}, {"missing or invalid fields", "brakujace lub bledne pola"}} {
		msg = strings.ReplaceAll(msg, r[0], r[1])
	}
	return msg
}
