<?php

namespace App\Services\AiFlows;

use App\Models\AiFlowExecution;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Collection;

class AiFlowAccessService
{
    public function isAdministrator(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public function canManageFlows(?User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function canExecuteFlows(?User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function canViewStrategicOutputs(?User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function canAccessEmpresa(?User $user, Empresa $empresa): bool
    {
        if (! $this->isAdministrator($user)) {
            return false;
        }

        return (int) $empresa->id > 0;
    }

    public function canAccessExecution(?User $user, AiFlowExecution $execution): bool
    {
        if (! $this->isAdministrator($user)) {
            return false;
        }

        $execution->loadMissing('empresa');

        return $execution->empresa instanceof Empresa;
    }

    /**
     * @return Collection<int, Empresa>
     */
    public function authorizedEmpresas(?User $user): Collection
    {
        if (! $this->isAdministrator($user)) {
            return collect();
        }

        return Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }
}
