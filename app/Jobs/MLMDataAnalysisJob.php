<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MLM\MLMDataCleaningService;
use App\Models\MLMCleaningSession;
use Illuminate\Support\Facades\Log;

class MLMDataAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $sessionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting MLM data analysis job for session ID: {$this->sessionId}");

        $session = MLMCleaningSession::find($this->sessionId);

        if (!$session) {
            Log::error("MLM cleaning session not found: {$this->sessionId}");
            return;
        }

        try {
            $cleaningService = new MLMDataCleaningService();

            // Réinitialiser le service avec la session existante
            $cleaningService->setSession($session);

            // Exécuter l'analyse
            $cleaningService->execute($session, [
                'preview_only' => true
            ]);

            Log::info("MLM data analysis completed for session: {$session->session_code}");

        } catch (\Exception $e) {
            Log::error("MLM data analysis failed for session {$session->session_code}: " . $e->getMessage());

            $session->updateStatus(MLMCleaningSession::STATUS_FAILED, $e->getMessage());

            throw $e; // Re-throw pour que le job soit marqué comme échoué
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['mlm-cleaning', 'analysis', "session:{$this->sessionId}"];
    }
}
