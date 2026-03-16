<?php

namespace App\Http\Livewire\Admin\System;

use App\Services\Google\GoogleConnectionHealthService;
use App\Services\Google\GoogleConnectionHealthStatus;
use Livewire\Component;

class GoogleIntegrationStatus extends Component
{
    /** @var bool */
    public $clientIdConfigured = false;

    /** @var bool */
    public $redirectUriConfigured = false;

    /** @var bool */
    public $refreshTokenConfigured = false;

    /** @var bool */
    public $connectionTestPassed = false;

    /** @var string */
    public $healthState = GoogleConnectionHealthStatus::STATE_NOT_CONFIGURED;

    /** @var string|null */
    public $lastCheckedAt;

    /** @var bool */
    public $hasLastKnownStatus = false;

    /** @var string|null */
    public $lastKnownHealthState;

    /** @var string|null */
    public $lastKnownCheckedAt;

    public function mount(GoogleConnectionHealthService $healthService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403);
        }

        $this->loadStatus($healthService);
    }

    public function refreshStatus(GoogleConnectionHealthService $healthService): void
    {
        $this->loadStatus($healthService);

        session()->flash('google_health_status_refreshed', 'Diagnóstico actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.system.google-integration-status');
    }

    private function loadStatus(GoogleConnectionHealthService $healthService): void
    {
        $this->syncConfigurationFlags();
        $this->syncLastKnownStatus($healthService->lastKnownStatus());
        $this->syncCurrentStatus($healthService->diagnose());
    }

    private function syncConfigurationFlags(): void
    {
        $oauth = (array) config('google.oauth', []);

        $this->clientIdConfigured = $this->hasConfiguredValue($oauth, 'client_id');
        $this->redirectUriConfigured = $this->hasConfiguredValue($oauth, 'redirect_uri');
        $this->refreshTokenConfigured = $this->hasConfiguredValue($oauth, 'refresh_token');
    }

    private function syncCurrentStatus(GoogleConnectionHealthStatus $status): void
    {
        $this->healthState = $status->state;
        $this->connectionTestPassed = $status->isConnected();
        $this->lastCheckedAt = isset($status->meta['checked_at']) ? (string) $status->meta['checked_at'] : null;
    }

    private function syncLastKnownStatus(?GoogleConnectionHealthStatus $status): void
    {
        if (! $status) {
            $this->hasLastKnownStatus = false;
            $this->lastKnownHealthState = null;
            $this->lastKnownCheckedAt = null;

            return;
        }

        $this->hasLastKnownStatus = true;
        $this->lastKnownHealthState = $status->state;
        $this->lastKnownCheckedAt = isset($status->meta['checked_at']) ? (string) $status->meta['checked_at'] : null;
    }

    private function hasConfiguredValue(array $config, string $key): bool
    {
        if (! isset($config[$key])) {
            return false;
        }

        return trim((string) $config[$key]) !== '';
    }
}
