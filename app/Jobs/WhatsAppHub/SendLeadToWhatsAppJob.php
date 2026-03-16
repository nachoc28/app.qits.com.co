<?php

namespace App\Jobs\WhatsAppHub;

use App\Services\WhatsAppHub\WhatsAppDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLeadToWhatsAppJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $leadId;
    public ?int $ruleId;

    public int $tries = 3;

    public function __construct(int $leadId, ?int $ruleId = null)
    {
        $this->leadId = $leadId;
        $this->ruleId = $ruleId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(WhatsAppDispatchService $dispatchService): void
    {
        $dispatchService->dispatchNowByIds($this->leadId, $this->ruleId);
    }
}
