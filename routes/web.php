<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgentEnrollmentCodeController;
use App\Http\Controllers\AgentStatusController;
use App\Http\Controllers\AgentWindowsServiceController;
use App\Http\Controllers\AvailabilityReportController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceManagementController;
use App\Http\Controllers\FacilityAgentPackageController;
use App\Http\Controllers\FacilityManagementController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MonitoringCenterController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PanelAuthController;
use App\Http\Controllers\SecurityAuditLogController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\SystemStatusController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/legal.php';

Route::get('/panel/login', [PanelAuthController::class, 'show'])
    ->name('panel.login');

Route::redirect('/login', '/panel/login')
    ->name('login');

Route::post('/panel/login', [PanelAuthController::class, 'login'])
    ->name('panel.login.store');

Route::post('/panel/logout', [PanelAuthController::class, 'logout'])
    ->middleware('panel.auth')
    ->name('panel.logout');

Route::middleware('panel.auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/konto', [AccountController::class, 'edit'])
        ->name('account.edit');

    Route::put('/konto/haslo', [AccountController::class, 'update'])
        ->name('account.update');

    Route::get('/centrum-monitoringu', [MonitoringCenterController::class, 'index'])
        ->name('monitoring-center.index');

    Route::get('/centrum-monitoringu/dane', [MonitoringCenterController::class, 'snapshot'])
        ->name('monitoring-center.snapshot');

    Route::get('/raporty', [AvailabilityReportController::class, 'index'])
        ->name('reports.index');

    Route::get('/agenci', AgentStatusController::class)
        ->name('agents.index');

    Route::get('/incydenty', [IncidentController::class, 'index'])
        ->name('incidents.index');

    Route::get('/incydenty/{incident}', [IncidentController::class, 'show'])
        ->whereNumber('incident')
        ->name('incidents.show');

    Route::post('/incydenty/{incident}/potwierdz', [IncidentController::class, 'acknowledge'])
        ->whereNumber('incident')
        ->name('incidents.acknowledge');

    Route::post('/incydenty/{incident}/w-trakcie', [IncidentController::class, 'startProgress'])
        ->whereNumber('incident')
        ->name('incidents.in-progress');

    Route::patch('/incydenty/{incident}/przypisz', [IncidentController::class, 'assign'])
        ->whereNumber('incident')
        ->name('incidents.assign');

    Route::post('/incydenty/{incident}/komentarze', [IncidentController::class, 'comment'])
        ->whereNumber('incident')
        ->name('incidents.comments.store');

    Route::post('/incydenty/{incident}/rozwiaz', [IncidentController::class, 'resolve'])
        ->whereNumber('incident')
        ->name('incidents.resolve');

    Route::post('/incydenty/{incident}/zamknij', [IncidentController::class, 'close'])
        ->whereNumber('incident')
        ->name('incidents.close');

    Route::get('/placowki/{facility}/raport', [AvailabilityReportController::class, 'facility'])
        ->middleware('facility.access')
        ->name('reports.facility');

    Route::get('/urzadzenia/{device}/heartbeaty', [DashboardController::class, 'deviceHeartbeats'])
        ->middleware('facility.access')
        ->name('devices.heartbeats');

    Route::middleware('panel.admin')->group(function (): void {
        Route::get('/stan-systemu', [SystemStatusController::class, 'index'])
            ->name('system.status');

        Route::get('/kopie-zapasowe', [BackupController::class, 'index'])
            ->name('backups.index');

        Route::post('/kopie-zapasowe', [BackupController::class, 'store'])
            ->name('backups.store');

        Route::post('/kopie-zapasowe/{backupRun}/sprawdz', [BackupController::class, 'verify'])
            ->whereNumber('backupRun')
            ->name('backups.verify');

        Route::get('/ustawienia', [SystemSettingsController::class, 'edit'])
            ->name('system-settings.edit');

        Route::put('/ustawienia', [SystemSettingsController::class, 'update'])
            ->name('system-settings.update');

        Route::get('/ustawienia/uslugi-windows', [AgentWindowsServiceController::class, 'index'])
            ->name('agent-windows-services.index');

        Route::post('/ustawienia/uslugi-windows', [AgentWindowsServiceController::class, 'store'])
            ->name('agent-windows-services.store');

        Route::put('/ustawienia/uslugi-windows/{agentWindowsService}', [AgentWindowsServiceController::class, 'update'])
            ->name('agent-windows-services.update');

        Route::delete('/ustawienia/uslugi-windows/{agentWindowsService}', [AgentWindowsServiceController::class, 'destroy'])
            ->name('agent-windows-services.destroy');

        Route::get('/operatorzy', [OperatorController::class, 'index'])
            ->name('operators.index');

        Route::get('/operatorzy/dodaj', [OperatorController::class, 'create'])
            ->name('operators.create');

        Route::post('/operatorzy', [OperatorController::class, 'store'])
            ->name('operators.store');

        Route::get('/operatorzy/{operator}/edytuj', [OperatorController::class, 'edit'])
            ->name('operators.edit');

        Route::put('/operatorzy/{operator}', [OperatorController::class, 'update'])
            ->name('operators.update');

        Route::get('/bezpieczenstwo/dziennik', [SecurityAuditLogController::class, 'index'])
            ->name('security-audit.index');

        Route::get('/placowki/dodaj', [FacilityAgentPackageController::class, 'create'])
            ->name('facilities.create');

        Route::post('/placowki/dodaj', [FacilityAgentPackageController::class, 'store'])
            ->name('facilities.store');

        Route::get('/placowki/{facility}/zarzadzaj', [FacilityManagementController::class, 'edit'])
            ->name('facilities.manage');

        Route::patch('/placowki/{facility}/zarzadzaj', [FacilityManagementController::class, 'update'])
            ->name('facilities.update');

        Route::post('/placowki/{facility}/dezaktywuj', [FacilityManagementController::class, 'deactivate'])
            ->name('facilities.deactivate');

        Route::post('/placowki/{facility}/aktywuj', [FacilityManagementController::class, 'activate'])
            ->name('facilities.activate');

        Route::get('/placowki/{facility}/urzadzenia/dodaj', [FacilityAgentPackageController::class, 'createDevice'])
            ->name('facilities.devices.create');

        Route::post('/placowki/{facility}/urzadzenia/dodaj', [FacilityAgentPackageController::class, 'storeDevice'])
            ->name('facilities.devices.store');

        Route::get('/urzadzenia/{device}/zarzadzaj', [DeviceManagementController::class, 'edit'])
            ->name('devices.edit');

        Route::post('/urzadzenia/{device}/kod-instalacyjny', [AgentEnrollmentCodeController::class, 'store'])
            ->name('devices.enrollment-codes.store');

        Route::delete('/urzadzenia/{device}/kod-instalacyjny/{enrollmentCode}', [AgentEnrollmentCodeController::class, 'revoke'])
            ->name('devices.enrollment-codes.revoke');

        Route::get('/agent/PlacowkaOnlineSetup.exe', [AgentEnrollmentCodeController::class, 'downloadSetup'])
            ->name('agent-installer.download');

        Route::patch('/urzadzenia/{device}/zarzadzaj', [DeviceManagementController::class, 'update'])
            ->name('devices.update');

        Route::post('/urzadzenia/{device}/dezaktywuj', [DeviceManagementController::class, 'deactivate'])
            ->name('devices.deactivate');

        Route::post('/urzadzenia/{device}/aktywuj', [DeviceManagementController::class, 'activate'])
            ->name('devices.activate');

        Route::post('/urzadzenia/{device}/archiwizuj', [DeviceManagementController::class, 'archive'])
            ->name('devices.archive');

        Route::post('/urzadzenia/{device}/regeneruj-paczke', [DeviceManagementController::class, 'regeneratePackage'])
            ->name('devices.regenerate-package');

        Route::get('/paczki-agentow/{zipName}', [FacilityAgentPackageController::class, 'download'])
            ->name('agent-packages.download');
    });

    Route::get('/placowki/{facility}', [DashboardController::class, 'show'])
        ->whereNumber('facility')
        ->middleware('facility.access')
        ->name('facilities.show');
});
