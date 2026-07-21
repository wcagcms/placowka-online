<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Services\SecurityAuditLogger;
use App\Services\SystemBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class BackupController extends Controller
{
    public function index(): View
    {
        $runs = BackupRun::query()->latest('started_at')->paginate(30);
        $latestSuccessful = BackupRun::query()
            ->where('status', 'success')
            ->whereNull('deleted_at')
            ->latest('completed_at')
            ->first();

        return view('backups.index', compact('runs', 'latestSuccessful'));
    }

    public function store(
        Request $request,
        SystemBackupService $backups,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        try {
            $run = $backups->create();

            $audit->write('backup.created_manually', $request->user(), $run, [
                'backup_id' => $run->id,
                'status' => $run->status,
            ], $request);

            return back()->with('success', 'Kopia została utworzona i zweryfikowana.');
        } catch (Throwable $exception) {
            return back()->withErrors([
                'backup' => 'Nie udało się utworzyć kopii: '.$exception->getMessage(),
            ]);
        }
    }

    public function verify(
        Request $request,
        BackupRun $backupRun,
        SystemBackupService $backups,
        SecurityAuditLogger $audit
    ): RedirectResponse {
        try {
            $backups->verify($backupRun);

            $audit->write('backup.verified_manually', $request->user(), $backupRun, [
                'backup_id' => $backupRun->id,
                'status' => 'success',
            ], $request);

            return back()->with('success', 'Suma SHA-256 i zawartość kopii są prawidłowe.');
        } catch (Throwable $exception) {
            return back()->withErrors([
                'backup' => 'Weryfikacja kopii nie powiodła się: '.$exception->getMessage(),
            ]);
        }
    }
}
