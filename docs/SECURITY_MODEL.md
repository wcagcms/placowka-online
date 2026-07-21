# Model bezpieczeństwa

## Założenia

- serwer jest dostępny przez HTTPS;
- konto administratora i skrzynka pocztowa są chronione MFA, gdy dostawca to
  umożliwia;
- token każdego urządzenia jest unikalny;
- klucz Code Signing nie znajduje się na serwerze aplikacji ani w repozytorium;
- operator widzi tylko przypisane placówki.

## Granice zaufania

1. komputer Windows i lokalna instalacja agenta;
2. Internet i terminacja TLS;
3. aplikacja Laravel;
4. baza danych i prywatny storage;
5. stanowisko wydawnicze z certyfikatem Code Signing.

## Ryzyka i zabezpieczenia

- przejęcie kodu rejestracyjnego — krótki TTL, pojedyncze użycie i limity prób;
- powtórzenie tokenu — indywidualne tokeny i kontrola replay w enrollment;
- utrata sieci — ograniczona kolejka offline;
- fałszywe dane historyczne — rozdzielenie czasu pomiaru i dostarczenia;
- wyciek repozytorium — brak sekretów, baz, logów, EXE i materiałów podpisu;
- podszycie się pod instalator — podpis Authenticode i SHA-256.
