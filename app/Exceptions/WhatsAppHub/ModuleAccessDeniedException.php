<?php

namespace App\Exceptions\WhatsAppHub;

use RuntimeException;
use App\Models\Empresa;

/**
 * Se lanza cuando una empresa no tiene el servicio requerido activo
 * o la empresa misma no está activa.
 */
class ModuleAccessDeniedException extends RuntimeException
{
    private Empresa $empresa;
    private string  $module;

    public function __construct(Empresa $empresa, string $module, string $reason = '')
    {
        $this->empresa = $empresa;
        $this->module  = $module;

        $message = "Acceso denegado al módulo [{$module}] para la empresa [{$empresa->nombre}]";
        if ($reason !== '') {
            $message .= ": {$reason}";
        }

        parent::__construct($message);
    }

    public function getEmpresa(): Empresa
    {
        return $this->empresa;
    }

    public function getModule(): string
    {
        return $this->module;
    }
}
