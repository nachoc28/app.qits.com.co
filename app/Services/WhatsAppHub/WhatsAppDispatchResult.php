<?php

namespace App\Services\WhatsAppHub;

final class WhatsAppDispatchResult
{
    public bool $queued;
    public bool $outboundLogged;
    public bool $textAttempted;
    public bool $textSent;
    public bool $textFailed;
    public bool $documentAttempted;
    public bool $documentSent;
    public bool $documentFailed;
    public ?string $error;

    public function __construct(
        bool $queued = false,
        bool $outboundLogged = false,
        bool $textAttempted = false,
        bool $textSent = false,
        bool $textFailed = false,
        bool $documentAttempted = false,
        bool $documentSent = false,
        bool $documentFailed = false,
        ?string $error = null
    ) {
        $this->queued = $queued;
        $this->outboundLogged = $outboundLogged;
        $this->textAttempted = $textAttempted;
        $this->textSent = $textSent;
        $this->textFailed = $textFailed;
        $this->documentAttempted = $documentAttempted;
        $this->documentSent = $documentSent;
        $this->documentFailed = $documentFailed;
        $this->error = $error;
    }

    public function hasAnySent(): bool
    {
        return $this->textSent || $this->documentSent;
    }

    public function hasFailures(): bool
    {
        return $this->textFailed || $this->documentFailed || $this->error !== null;
    }
}
