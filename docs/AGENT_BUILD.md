# Budowa agenta Windows

## Wymagania

- Go 1.26.5 lub nowszy;
- Linux do cross-kompilacji agenta;
- Windows z Inno Setup do utworzenia instalatora.

## Źródła

```text
storage/app/agent-template/src/main.go
storage/app/agent-installer/src/enroll/main.go
storage/app/agent-installer/inno/PlacowkaOnlineSetup.iss
```

## Budowa BuildInput

```bash
export PATH="$HOME/.local/go1.26.5/bin:$PATH"
bash scripts/build-agent-v1.9.2.sh "$PWD"
```

Wygenerowane pliki binarne i paczki są ignorowane przez Git.

## Inno Setup

Na Windows rozpakuj BuildInput, przejdź do `inno` i uruchom dołączony skrypt
budowania. Instalator należy podpisać przed publikacją.

## Test

```powershell
& "C:\PlacowkaOnline\PlacowkaOnlineAgentConsole.exe"
Get-ScheduledTaskInfo -TaskName PlacowkaOnlineAgent
```
