#define AppName "Placówka Online Agent"
#define AppVersion "1.0.5"
#define AgentVersion "exe-1.9.2"
#define PublisherName "Placówka Online"
#define ServerUrl "https://monitoring.wcag-cms.pl"

[Setup]
AppId={{1EDC7B07-D3BE-4B9B-91BC-4EBFF0BFE461}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#PublisherName}
AppPublisherURL={#ServerUrl}
AppSupportURL={#ServerUrl}
DefaultDirName={sd}\PlacowkaOnline
DisableDirPage=yes
DisableProgramGroupPage=yes
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
OutputDir=output
OutputBaseFilename=PlacowkaOnlineSetup
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
SetupLogging=yes
UninstallDisplayName={#AppName}
UninstallFilesDir={app}\uninstall
CloseApplications=yes
RestartApplications=no
ChangesEnvironment=no
MinVersion=10.0.17763

[Languages]
Name: "polish"; MessagesFile: "compiler:Languages\Polish.isl"

[Dirs]
Name: "{app}"; Permissions: admins-full system-full
Name: "{app}\logs"; Permissions: admins-full system-full

[Files]
Source: "build-input\PlacowkaOnlineAgent.exe"; DestDir: "{app}"; Flags: ignoreversion restartreplace
Source: "build-input\PlacowkaOnlineAgentConsole.exe"; DestDir: "{app}"; Flags: ignoreversion restartreplace
Source: "build-input\BUILD_INFO.txt"; DestDir: "{app}"; Flags: ignoreversion
Source: "build-input\PlacowkaOnlineEnroll.exe"; Flags: dontcopy

[UninstallRun]
Filename: "{sys}\schtasks.exe"; Parameters: "/End /TN PlacowkaOnlineAgent"; Flags: runhidden waituntilterminated; RunOnceId: "StopAgentTask"
Filename: "{sys}\schtasks.exe"; Parameters: "/Delete /TN PlacowkaOnlineAgent /F"; Flags: runhidden waituntilterminated; RunOnceId: "DeleteAgentTask"

[UninstallDelete]
Type: filesandordirs; Name: "{app}"

[Code]
var
  EnrollmentPage: TInputQueryWizardPage;
  EnrollmentStarted: Boolean;
  StateFile: String;
  ErrorFile: String;

function ReadInstallerError(): String;
var
  ErrorText: AnsiString;
begin
  Result := 'Nie udało się wykonać bezpiecznej rejestracji agenta.';

  if FileExists(ErrorFile) and LoadStringFromFile(ErrorFile, ErrorText) then
  begin
    Result := ErrorText;
  end;

  Result := Trim(Result);
  if Result = '' then
    Result := 'Nie udało się wykonać bezpiecznej rejestracji agenta.';
end;

function NormalizedCodeLength(const Value: String): Integer;
var
  I: Integer;
  C: Char;
begin
  Result := 0;
  for I := 1 to Length(Value) do
  begin
    C := Value[I];
    if (C >= 'a') and (C <= 'z') then
      C := Chr(Ord(C) - 32);

    if ((C >= 'A') and (C <= 'Z')) or ((C >= '0') and (C <= '9')) then
      Result := Result + 1;
  end;
end;

procedure InitializeWizard();
begin
  EnrollmentPage := CreateInputQueryPage(
    wpSelectDir,
    'Rejestracja urządzenia',
    'Wprowadź jednorazowy kod instalacyjny',
    'Kod wygenerujesz w panelu Placówka Online. Jest ważny 15 minut i może zostać użyty tylko raz.'
  );
  EnrollmentPage.Add('Kod urządzenia:', False);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;

  if CurPageID = EnrollmentPage.ID then
  begin
    if (NormalizedCodeLength(Trim(EnrollmentPage.Values[0])) < 10) or
       (NormalizedCodeLength(Trim(EnrollmentPage.Values[0])) > 24) then
    begin
      MsgBox('Wpisz pełny kod urządzenia, np. PP10-7K3M-92XR.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  CodeFile: String;
  HelperPath: String;
  ExitCode: Integer;
  Parameters: String;
begin
  Result := '';
  NeedsRestart := False;

  StateFile := ExpandConstant('{tmp}\PlacowkaOnlineEnrollment.state.json');
  ErrorFile := ExpandConstant('{tmp}\PlacowkaOnlineEnrollment.error.txt');
  CodeFile := ExpandConstant('{tmp}\PlacowkaOnlineEnrollment.code.txt');
  HelperPath := ExpandConstant('{tmp}\PlacowkaOnlineEnroll.exe');

  DeleteFile(StateFile);
  DeleteFile(ErrorFile);
  DeleteFile(CodeFile);

  if not SaveStringToFile(CodeFile, Utf8Encode(Trim(EnrollmentPage.Values[0])), False) then
  begin
    Result := 'Nie udało się przygotować kodu instalacyjnego.';
    exit;
  end;

  ExtractTemporaryFile('PlacowkaOnlineEnroll.exe');

  Parameters :=
    'start' +
    ' --server "{#ServerUrl}"' +
    ' --code-file "' + CodeFile + '"' +
    ' --state-file "' + StateFile + '"' +
    ' --error-file "' + ErrorFile + '"' +
    ' --setup-version "{#AppVersion}"';

  if not Exec(HelperPath, Parameters, '', SW_HIDE, ewWaitUntilTerminated, ExitCode) then
  begin
    Result := 'Nie udało się uruchomić modułu bezpiecznej rejestracji.';
    exit;
  end;

  if ExitCode <> 0 then
  begin
    Result := ReadInstallerError();
    exit;
  end;

  EnrollmentStarted := True;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  HelperPath: String;
  ExitCode: Integer;
  Parameters: String;
begin
  if (CurStep = ssPostInstall) and EnrollmentStarted then
  begin
    HelperPath := ExpandConstant('{tmp}\PlacowkaOnlineEnroll.exe');
    DeleteFile(ErrorFile);

    WizardForm.StatusLabel.Caption :=
      'Pełny pomiar inicjalny, wysyłanie danych i uruchamianie profilu low_impact...';

    Parameters :=
      'complete' +
      ' --state-file "' + StateFile + '"' +
      ' --target-dir "' + ExpandConstant('{app}') + '"' +
      ' --error-file "' + ErrorFile + '"' +
      ' --agent-version "{#AgentVersion}"' +
      ' --task-name "PlacowkaOnlineAgent"';

    if not Exec(HelperPath, Parameters, '', SW_HIDE, ewWaitUntilTerminated, ExitCode) then
      RaiseException('Nie udało się uruchomić drugiego etapu rejestracji.');

    if ExitCode <> 0 then
      RaiseException(ReadInstallerError());

    WizardForm.StatusLabel.Caption :=
      'Pełny pomiar został wysłany. Agent działa w profilu low_impact.';
  end;
end;

procedure DeinitializeSetup();
begin
  if StateFile <> '' then
    DeleteFile(StateFile);
  if ErrorFile <> '' then
    DeleteFile(ErrorFile);
end;
