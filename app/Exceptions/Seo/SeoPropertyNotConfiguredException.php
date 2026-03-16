<?php

namespace App\Exceptions\Seo;

use App\Models\Empresa;
use RuntimeException;

/**
 * Se lanza cuando se intenta operar con el módulo SEO de una empresa
 * que todavía no tiene EmpresaSeoProperty registrado.
 */
class SeoPropertyNotConfiguredException extends RuntimeException
{
    public function __construct(Empresa $empresa)
    {
        parent::__construct(
            "La empresa [{$empresa->id}] no tiene propiedad SEO configurada."
        );
    }
}
