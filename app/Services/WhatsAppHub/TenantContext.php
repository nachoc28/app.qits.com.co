<?php

namespace App\Services\WhatsAppHub;

use App\Models\Empresa;
use App\Models\FormForwardingRule;

/**
 * Objeto de valor inmutable que encapsula el resultado de la resolución
 * de tenant. Evita pasar $empresa y $rule por separado entre capas.
 */
final class TenantContext
{
    private Empresa $empresa;
    private FormForwardingRule $rule;

    public function __construct(Empresa $empresa, FormForwardingRule $rule)
    {
        $this->empresa = $empresa;
        $this->rule    = $rule;
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function getRule(): FormForwardingRule
    {
        return $this->rule;
    }
}
