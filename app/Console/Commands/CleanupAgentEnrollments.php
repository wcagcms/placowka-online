<?php

namespace App\Console\Commands;

use App\Models\AgentEnrollmentCode;
use App\Models\AgentEnrollmentSession;
use Illuminate\Console\Command;

class CleanupAgentEnrollments extends Command
{
    protected $signature = 'placowka:cleanup-enrollments {--days=90 : Retencja metadanych kodów}';

    protected $description = 'Usuwa wygasłe sekrety sesji i stare metadane rejestracji agentów.';

    public function handle(): int
    {
        $days = max(7, min(365, (int) $this->option('days')));

        $clearedSecrets = AgentEnrollmentSession::query()
            ->whereNotNull('issued_token_ciphertext')
            ->where(function ($query): void {
                $query->where('token_replay_until', '<', now())
                    ->orWhereNotNull('confirmed_at');
            })
            ->update([
                'issued_token_ciphertext' => null,
                'token_replay_until' => null,
                'updated_at' => now(),
            ]);

        $deletedSessions = AgentEnrollmentSession::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $deletedCodes = AgentEnrollmentCode::query()
            ->where('created_at', '<', now()->subDays($days))
            ->where(function ($query): void {
                $query->whereNotNull('used_at')
                    ->orWhereNotNull('revoked_at')
                    ->orWhere('expires_at', '<', now()->subDays(1));
            })
            ->delete();

        $this->info("Wyczyszczono sekretów: {$clearedSecrets}; sesji: {$deletedSessions}; kodów: {$deletedCodes}.");

        return self::SUCCESS;
    }
}
