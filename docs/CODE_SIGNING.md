# Open Source Code Signing

Certyfikat służy wyłącznie do podpisywania oficjalnych plików projektu:

- `PlacowkaOnlineSetup.exe`;
- `PlacowkaOnlineAgent.exe`;
- `PlacowkaOnlineAgentConsole.exe`;
- `PlacowkaOnlineEnroll.exe`.

## Zasady

- klucz prywatny nigdy nie trafia do Git, `.env`, serwera WWW ani paczki źródłowej;
- podpis wykonywany jest na kontrolowanym stanowisku wydawniczym;
- przed podpisem wykonywane są testy i skan antywirusowy;
- używany jest znacznik czasu;
- po podpisie weryfikowany jest status Authenticode i suma SHA-256;
- wydanie GitHub wskazuje commit/tag odpowiadający binariom.

Weryfikacja:

```powershell
Get-AuthenticodeSignature .\PlacowkaOnlineSetup.exe |
  Select-Object Status,StatusMessage,@{n='Wydawca';e={$_.SignerCertificate.Subject}}

Get-FileHash .\PlacowkaOnlineSetup.exe -Algorithm SHA256
```

Oczekiwany status podpisu: `Valid`.
