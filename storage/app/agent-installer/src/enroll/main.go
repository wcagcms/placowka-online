package main

import (
	"bytes"
	"context"
	"crypto/rand"
	"crypto/tls"
	"encoding/base64"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

const (
	productName      = "Placówka Online"
	defaultServerURL = "https://monitoring.wcag-cms.pl"
	maxResponseBytes = 64 * 1024
)

var buildVersion = "dev"

type startRequest struct {
	Code           string `json:"code"`
	MachineName    string `json:"machine_name"`
	ClientNonce    string `json:"client_nonce"`
	Architecture   string `json:"architecture"`
	WindowsVersion string `json:"windows_version,omitempty"`
	SetupVersion   string `json:"setup_version"`
}

type startResponse struct {
	OK           bool   `json:"ok"`
	Status       string `json:"status"`
	EnrollmentID string `json:"enrollment_id"`
	SessionToken string `json:"session_token"`
	ExpiresAt    string `json:"expires_at"`
	Message      string `json:"message"`
	Device       struct {
		UUID         string `json:"uuid"`
		Name         string `json:"name"`
		FacilityCode string `json:"facility_code"`
		FacilityName string `json:"facility_name"`
	} `json:"device"`
}

type enrollmentState struct {
	ServerURL      string `json:"server_url"`
	EnrollmentID   string `json:"enrollment_id"`
	SessionToken   string `json:"session_token"`
	ClientNonce    string `json:"client_nonce"`
	MachineName    string `json:"machine_name"`
	Architecture   string `json:"architecture"`
	WindowsVersion string `json:"windows_version"`
	SetupVersion   string `json:"setup_version"`
	ExpiresAt      string `json:"expires_at"`
	DeviceUUID     string `json:"device_uuid"`
	DeviceName     string `json:"device_name"`
	FacilityCode   string `json:"facility_code"`
	FacilityName   string `json:"facility_name"`
}

type completeRequest struct {
	EnrollmentID   string `json:"enrollment_id"`
	SessionToken   string `json:"session_token"`
	ClientNonce    string `json:"client_nonce"`
	MachineName    string `json:"machine_name"`
	Architecture   string `json:"architecture"`
	WindowsVersion string `json:"windows_version,omitempty"`
	SetupVersion   string `json:"setup_version"`
	AgentVersion   string `json:"agent_version"`
}

type completeResponse struct {
	OK            bool            `json:"ok"`
	Status        string          `json:"status"`
	Message       string          `json:"message"`
	Configuration json.RawMessage `json:"configuration"`
}

type apiError struct {
	Message string `json:"message"`
}

func main() {
	if len(os.Args) < 2 {
		fatalWithFile("", "Brak trybu pracy modułu rejestracyjnego.")
	}

	var err error
	switch strings.ToLower(os.Args[1]) {
	case "start":
		err = runStart(os.Args[2:])
	case "complete":
		err = runComplete(os.Args[2:])
	case "version":
		fmt.Printf("%s enrollment helper %s\n", productName, buildVersion)
		return
	default:
		err = fmt.Errorf("nieznany tryb: %s", os.Args[1])
	}

	if err != nil {
		fmt.Fprintln(os.Stderr, err.Error())
		os.Exit(1)
	}
}

func runStart(args []string) error {
	flags := flag.NewFlagSet("start", flag.ContinueOnError)
	server := flags.String("server", defaultServerURL, "Adres serwera")
	codeFile := flags.String("code-file", "", "Plik z kodem jednorazowym")
	stateFile := flags.String("state-file", "", "Plik sesji instalacyjnej")
	errorFile := flags.String("error-file", "", "Plik komunikatu błędu")
	setupVersion := flags.String("setup-version", buildVersion, "Wersja instalatora")
	if err := flags.Parse(args); err != nil {
		return err
	}
	defer clearErrorFile(*errorFile)

	if *codeFile == "" || *stateFile == "" {
		return writeFailure(*errorFile, "Instalator nie przekazał wymaganych plików roboczych.")
	}

	serverURL, err := validateServerURL(*server)
	if err != nil {
		return writeFailure(*errorFile, err.Error())
	}

	codeBytes, err := os.ReadFile(*codeFile)
	_ = os.Remove(*codeFile)
	if err != nil {
		return writeFailure(*errorFile, "Nie udało się odczytać kodu instalacyjnego.")
	}
	code := strings.TrimSpace(string(codeBytes))
	if len(code) < 10 || len(code) > 40 {
		return writeFailure(*errorFile, "Kod instalacyjny ma nieprawidłowy format.")
	}

	machineName, err := os.Hostname()
	if err != nil || strings.TrimSpace(machineName) == "" {
		return writeFailure(*errorFile, "Nie udało się odczytać nazwy komputera.")
	}

	nonce, err := randomSecret(48)
	if err != nil {
		return writeFailure(*errorFile, "Nie udało się utworzyć bezpiecznej sesji lokalnej.")
	}

	request := startRequest{
		Code:           code,
		MachineName:    machineName,
		ClientNonce:    nonce,
		Architecture:   runtime.GOARCH,
		WindowsVersion: windowsVersion(),
		SetupVersion:   *setupVersion,
	}

	var response startResponse
	if err := postJSON(serverURL+"/api/v1/agent/enroll/start", request, &response); err != nil {
		return writeFailure(*errorFile, err.Error())
	}
	if !response.OK || response.EnrollmentID == "" || response.SessionToken == "" {
		message := response.Message
		if message == "" {
			message = "Serwer nie rozpoczął rejestracji urządzenia."
		}
		return writeFailure(*errorFile, message)
	}

	state := enrollmentState{
		ServerURL:      serverURL,
		EnrollmentID:   response.EnrollmentID,
		SessionToken:   response.SessionToken,
		ClientNonce:    nonce,
		MachineName:    machineName,
		Architecture:   runtime.GOARCH,
		WindowsVersion: request.WindowsVersion,
		SetupVersion:   *setupVersion,
		ExpiresAt:      response.ExpiresAt,
		DeviceUUID:     response.Device.UUID,
		DeviceName:     response.Device.Name,
		FacilityCode:   response.Device.FacilityCode,
		FacilityName:   response.Device.FacilityName,
	}

	if err := writeJSONAtomic(*stateFile, state, 0600); err != nil {
		return writeFailure(*errorFile, "Nie udało się zapisać krótkotrwałej sesji instalacyjnej.")
	}
	_ = secureFile(*stateFile)

	return nil
}

func runComplete(args []string) error {
	flags := flag.NewFlagSet("complete", flag.ContinueOnError)
	stateFile := flags.String("state-file", "", "Plik sesji instalacyjnej")
	targetDir := flags.String("target-dir", `C:\PlacowkaOnline`, "Katalog instalacyjny")
	errorFile := flags.String("error-file", "", "Plik komunikatu błędu")
	agentVersion := flags.String("agent-version", "exe-1.8.0", "Wersja agenta")
	taskName := flags.String("task-name", "PlacowkaOnlineAgent", "Nazwa zadania")
	if err := flags.Parse(args); err != nil {
		return err
	}
	defer clearErrorFile(*errorFile)

	if *stateFile == "" || *targetDir == "" {
		return writeFailure(*errorFile, "Instalator nie przekazał danych drugiego etapu.")
	}

	var state enrollmentState
	stateBytes, err := os.ReadFile(*stateFile)
	if err != nil || json.Unmarshal(stateBytes, &state) != nil {
		return writeFailure(*errorFile, "Nie udało się odczytać sesji instalacyjnej. Wygeneruj nowy kod.")
	}

	request := completeRequest{
		EnrollmentID:   state.EnrollmentID,
		SessionToken:   state.SessionToken,
		ClientNonce:    state.ClientNonce,
		MachineName:    state.MachineName,
		Architecture:   state.Architecture,
		WindowsVersion: state.WindowsVersion,
		SetupVersion:   state.SetupVersion,
		AgentVersion:   *agentVersion,
	}

	var response completeResponse
	if err := postJSONWithRetry(state.ServerURL+"/api/v1/agent/enroll/complete", request, &response, 3); err != nil {
		return writeFailure(*errorFile, err.Error())
	}
	if !response.OK || len(response.Configuration) == 0 || string(response.Configuration) == "null" {
		message := response.Message
		if message == "" {
			message = "Serwer nie zakończył rejestracji urządzenia."
		}
		return writeFailure(*errorFile, message)
	}

	if err := os.MkdirAll(filepath.Join(*targetDir, "logs"), 0700); err != nil {
		return writeFailure(*errorFile, "Nie udało się utworzyć katalogu instalacyjnego.")
	}

	configPath := filepath.Join(*targetDir, "config.json")
	if err := writeRawJSONAtomic(configPath, response.Configuration, 0600); err != nil {
		return writeFailure(*errorFile, "Nie udało się bezpiecznie zapisać konfiguracji agenta.")
	}

	if err := secureDirectory(*targetDir); err != nil {
		return writeFailure(*errorFile, "Nie udało się zabezpieczyć katalogu agenta: "+err.Error())
	}

	agentPath := filepath.Join(*targetDir, "PlacowkaOnlineAgent.exe")
	if _, err := os.Stat(agentPath); err != nil {
		return writeFailure(*errorFile, "Brakuje pliku PlacowkaOnlineAgent.exe w katalogu instalacyjnym.")
	}

	consolePath := filepath.Join(*targetDir, "PlacowkaOnlineAgentConsole.exe")
	if _, err := os.Stat(consolePath); err != nil {
		return writeFailure(*errorFile, "Brakuje pliku PlacowkaOnlineAgentConsole.exe w katalogu instalacyjnym.")
	}

	// Poprzednia wersja agenta nie może uruchomić się równolegle z pomiarem
	// inicjalnym ani użyć właśnie obróconego tokenu urządzenia.
	stopAndDeleteScheduledTask(*taskName)

	// Pierwszy pomiar jest celowo pełny. Wersja konsolowa odświeża sieć,
	// system, dyski, usługi i SMART niezależnie od interwałów low_impact,
	// zapisuje state.json i wysyła jeden kompletny heartbeat.
	if err := runInitialFullMeasurement(consolePath, *targetDir, 5*time.Minute); err != nil {
		return writeFailure(*errorFile, "Nie udało się wysłać pełnego pomiaru inicjalnego: "+err.Error())
	}

	// Dopiero po poprawnym heartbeat rejestrujemy zwykły harmonogram.
	// Następne uruchomienia korzystają już z cache i profilu low_impact.
	if err := createScheduledTask(*taskName, agentPath); err != nil {
		return writeFailure(*errorFile, "Pełny pomiar został wysłany, ale nie udało się uruchomić trybu low_impact: "+err.Error())
	}

	_ = os.Remove(*stateFile)
	return nil
}

func postJSONWithRetry(endpoint string, payload any, target any, attempts int) error {
	if attempts < 1 {
		attempts = 1
	}

	var lastErr error
	for attempt := 1; attempt <= attempts; attempt++ {
		lastErr = postJSON(endpoint, payload, target)
		if lastErr == nil {
			return nil
		}
		if attempt < attempts {
			time.Sleep(time.Duration(attempt) * 2 * time.Second)
		}
	}

	return lastErr
}

func postJSON(endpoint string, payload any, target any) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return errors.New("Nie udało się przygotować żądania rejestracyjnego.")
	}

	request, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(body))
	if err != nil {
		return errors.New("Nieprawidłowy adres usługi rejestracyjnej.")
	}
	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("Accept", "application/json")
	request.Header.Set("User-Agent", "PlacowkaOnlineSetup/"+buildVersion)

	client := &http.Client{
		Timeout: 25 * time.Second,
		Transport: &http.Transport{
			Proxy: http.ProxyFromEnvironment,
			TLSClientConfig: &tls.Config{
				MinVersion: tls.VersionTLS12,
			},
		},
		CheckRedirect: func(_ *http.Request, _ []*http.Request) error {
			return errors.New("serwer rejestracyjny zwrócił niedozwolone przekierowanie")
		},
	}

	response, err := client.Do(request)
	if err != nil {
		return errors.New("Nie udało się połączyć z serwerem Placówka Online przez HTTPS.")
	}
	defer response.Body.Close()

	responseBytes, err := io.ReadAll(io.LimitReader(response.Body, maxResponseBytes+1))
	if err != nil || len(responseBytes) > maxResponseBytes {
		return errors.New("Odpowiedź serwera rejestracyjnego jest nieprawidłowa.")
	}

	if response.StatusCode < 200 || response.StatusCode >= 300 {
		var apiErr apiError
		_ = json.Unmarshal(responseBytes, &apiErr)
		if apiErr.Message != "" {
			return errors.New(apiErr.Message)
		}
		return fmt.Errorf("Serwer odrzucił operację rejestracji (HTTP %d).", response.StatusCode)
	}

	if err := json.Unmarshal(responseBytes, target); err != nil {
		return errors.New("Serwer zwrócił nieprawidłowy format odpowiedzi.")
	}

	return nil
}

func validateServerURL(raw string) (string, error) {
	parsed, err := url.Parse(strings.TrimSpace(raw))
	if err != nil || parsed.Scheme != "https" || parsed.Hostname() != "monitoring.wcag-cms.pl" {
		return "", errors.New("Instalator może łączyć się wyłącznie z https://monitoring.wcag-cms.pl.")
	}
	if parsed.Port() != "" && parsed.Port() != "443" {
		return "", errors.New("Niedozwolony port serwera rejestracyjnego.")
	}
	return strings.TrimRight(parsed.String(), "/"), nil
}

func randomSecret(bytesCount int) (string, error) {
	buffer := make([]byte, bytesCount)
	if _, err := rand.Read(buffer); err != nil {
		return "", err
	}
	return base64.RawURLEncoding.EncodeToString(buffer), nil
}

func windowsVersion() string {
	if runtime.GOOS != "windows" {
		return runtime.GOOS
	}
	output, err := exec.Command("cmd.exe", "/c", "ver").CombinedOutput()
	if err != nil {
		return "Windows"
	}
	value := strings.TrimSpace(string(output))
	if len(value) > 250 {
		value = value[:250]
	}
	return value
}

func stopAndDeleteScheduledTask(taskName string) {
	_ = exec.Command("schtasks.exe", "/End", "/TN", taskName).Run()
	_ = exec.Command("schtasks.exe", "/Delete", "/TN", taskName, "/F").Run()
}

func runInitialFullMeasurement(consolePath, workingDir string, timeout time.Duration) error {
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()

	command := exec.CommandContext(ctx, consolePath)
	command.Dir = workingDir
	output, err := command.CombinedOutput()

	if errors.Is(ctx.Err(), context.DeadlineExceeded) {
		return errors.New("przekroczono 5 minut oczekiwania na pełny pomiar")
	}

	if err != nil {
		message := strings.TrimSpace(string(output))
		if len(message) > 1200 {
			message = message[len(message)-1200:]
		}
		if message == "" {
			message = err.Error()
		}
		return errors.New(message)
	}

	return nil
}

func createScheduledTask(taskName, agentPath string) error {
	stopAndDeleteScheduledTask(taskName)

	taskCommand := fmt.Sprintf(`"%s"`, agentPath)
	command := exec.Command(
		"schtasks.exe",
		"/Create",
		"/TN", taskName,
		"/TR", taskCommand,
		"/SC", "MINUTE",
		"/MO", "1",
		"/RU", "SYSTEM",
		"/RL", "HIGHEST",
		"/F",
	)
	if output, err := command.CombinedOutput(); err != nil {
		return fmt.Errorf("%s", strings.TrimSpace(string(output)))
	}

	if output, err := exec.Command("schtasks.exe", "/Run", "/TN", taskName).CombinedOutput(); err != nil {
		return fmt.Errorf("zadanie utworzono, ale nie udało się go uruchomić: %s", strings.TrimSpace(string(output)))
	}

	return nil
}

func secureDirectory(directory string) error {
	commands := [][]string{
		{"takeown.exe", "/F", directory, "/A", "/R", "/D", "Y"},
		{"icacls.exe", directory, "/reset", "/T", "/C", "/Q"},
		{"icacls.exe", directory, "/inheritance:r", "/Q"},
		{"icacls.exe", directory, "/grant:r", "*S-1-5-18:(OI)(CI)F", "*S-1-5-32-544:(OI)(CI)F", "/Q"},
		{"icacls.exe", directory, "/setowner", "*S-1-5-18", "/T", "/C", "/Q"},
	}

	for _, item := range commands {
		command := exec.Command(item[0], item[1:]...)
		if output, err := command.CombinedOutput(); err != nil {
			return fmt.Errorf("%s: %s", item[0], strings.TrimSpace(string(output)))
		}
	}
	return nil
}

func secureFile(path string) error {
	if runtime.GOOS != "windows" {
		return nil
	}
	commands := [][]string{
		{"icacls.exe", path, "/inheritance:r", "/Q"},
		{"icacls.exe", path, "/grant:r", "*S-1-5-18:F", "*S-1-5-32-544:F", "/Q"},
	}
	for _, item := range commands {
		if output, err := exec.Command(item[0], item[1:]...).CombinedOutput(); err != nil {
			return fmt.Errorf("%s", strings.TrimSpace(string(output)))
		}
	}
	return nil
}

func writeJSONAtomic(path string, value any, mode os.FileMode) error {
	data, err := json.MarshalIndent(value, "", "  ")
	if err != nil {
		return err
	}
	return writeAtomic(path, append(data, '\n'), mode)
}

func writeRawJSONAtomic(path string, raw json.RawMessage, mode os.FileMode) error {
	var compact bytes.Buffer
	if err := json.Indent(&compact, raw, "", "  "); err != nil {
		return err
	}
	compact.WriteByte('\n')
	return writeAtomic(path, compact.Bytes(), mode)
}

func writeAtomic(path string, data []byte, mode os.FileMode) error {
	if err := os.MkdirAll(filepath.Dir(path), 0700); err != nil {
		return err
	}
	temporary := path + ".tmp"
	if err := os.WriteFile(temporary, data, mode); err != nil {
		return err
	}
	_ = os.Remove(path)
	return os.Rename(temporary, path)
}

func writeFailure(errorFile, message string) error {
	message = strings.TrimSpace(message)
	if message == "" {
		message = "Nieznany błąd instalatora."
	}
	if errorFile != "" {
		_ = os.WriteFile(errorFile, []byte(message+"\r\n"), 0600)
	}
	return errors.New(message)
}

func clearErrorFile(path string) {
	if path == "" {
		return
	}
	if info, err := os.Stat(path); err == nil && info.Size() == 0 {
		_ = os.Remove(path)
	}
}

func fatalWithFile(errorFile, message string) {
	_ = writeFailure(errorFile, message)
	fmt.Fprintln(os.Stderr, message)
	os.Exit(1)
}
