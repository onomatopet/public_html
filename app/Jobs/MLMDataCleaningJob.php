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

class MLMDataCleaningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $sessionId;
    protected array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(int $sessionId, array $options = [])
    {
        $this->sessionId = $sessionId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting MLM data cleaning job for session ID: {$this->sessionId}");

        $session = MLMCleaningSession::find($this->sessionId);

        if (!$session) {
            Log::error("MLM cleaning session not found: {$this->sessionId}");
            return;
        }

        try {
            $cleaningService = new MLMDataCleaningService();

            // Réinitialiser le service avec la session existante
            $cleaningService->setSession($session);

            // Exécuter le nettoyage
            $cleaningService->execute($session, $this->options);

            Log::info("MLM data cleaning completed for session: {$session->session_code}");

        } catch (\Exception $e) {
            Log::error("MLM data cleaning failed for session {$session->session_code}: " . $e->getMessage());

            $session->updateStatus(MLMCleaningSession::STATUS_FAILED, $e->getMessage());

            throw $e; // Re-throw pour que le job soit marqué comme échoué
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['mlm-cleaning', 'processing', "session:{$this->sessionId}"];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }
}
