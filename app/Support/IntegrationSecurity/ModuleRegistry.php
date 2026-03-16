<?php

namespace App\Support\IntegrationSecurity;

use InvalidArgumentException;

/**
 * Lee la configuración de módulos desde config/integration_security.php
 * y expone métodos de lookup tipados.
 *
 * Propósito:
 *   - Ser el único punto de acceso a la configuración de módulos para los
 *     servicios de autenticación y middleware.
 *   - Evitar que los controladores, jobs y middlewares lean el config directamente.
 *
 * Registro en el contenedor (opcional, se autoresuelve sin binding):
 *   $this->app->singleton(ModuleRegistry::class);
 *
 * Uso típico:
 *   $registry = app(ModuleRegistry::class);
 *   $scope    = $registry->scopeFor(IntegrationModule::MODULE1_FORM_INGRESS);
 *   $svcId    = $registry->requiredServiceIdFor(IntegrationModule::MODULE1_FORM_INGRESS);
 */
class ModuleRegistry
{
    /** @var array<string, array<string, mixed>> */
    private $modules;

    public function __construct()
    {
        $this->modules = config('integration_security.modules', []);
    }

    // ── Lookups básicos ───────────────────────────────────────────────────────

    /**
     * Comprueba si una clave de módulo está registrada en la configuración.
     */
    public function exists(string $moduleKey): bool
    {
        return isset($this->modules[$moduleKey]);
    }

    /**
     * Devuelve el array de configuración completo de un módulo.
     * Retorna null si la clave no existe en lugar de lanzar excepción.
     */
    public function find(string $moduleKey): ?array
    {
        return $this->modules[$moduleKey] ?? null;
    }

    /**
     * Devuelve el array de configuración completo de un módulo.
     *
     * @throws InvalidArgumentException si la clave no está registrada.
     */
    public function get(string $moduleKey): array
    {
        if (! $this->exists($moduleKey)) {
            throw new InvalidArgumentException(
                "Módulo no registrado en integration_security.modules: [{$moduleKey}]"
            );
        }

        return $this->modules[$moduleKey];
    }

    /**
     * Devuelve todos los módulos registrados.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
    }

    // ── Lookups por campo ─────────────────────────────────────────────────────

    /**
     * Scope técnico asociado al módulo.
     * Este valor es el que debe aparecer en EmpresaIntegration::$scopes_json.
     *
     * @throws InvalidArgumentException
     */
    public function scopeFor(string $moduleKey): string
    {
        return $this->get($moduleKey)['scope'];
    }

    /**
     * Id de servicio de catálogo (tabla servicios) que la empresa
     * debe tener activo para poder usar este módulo.
     *
     * @throws InvalidArgumentException
     */
    public function requiredServiceIdFor(string $moduleKey): int
    {
        return (int) $this->get($moduleKey)['required_service_id'];
    }

    /**
     * Slug del servicio requerido (alternativa desacoplada del id numérico).
     *
     * @throws InvalidArgumentException
     */
    public function requiredServiceSlugFor(string $moduleKey): string
    {
        return $this->get($moduleKey)['required_service_slug'];
    }

    /**
     * Descripción legible del módulo (útil para logs y UI de administración).
     *
     * @throws InvalidArgumentException
     */
    public function descriptionFor(string $moduleKey): string
    {
        return $this->get($moduleKey)['description'] ?? $moduleKey;
    }

    // ── Búsqueda inversa ──────────────────────────────────────────────────────

    /**
     * Dado un scope, devuelve la clave de módulo correspondiente.
     * Útil cuando el middleware recibe el scope del request y necesita
     * derivar las restricciones de servicio de negocio.
     *
     * @return string|null clave de módulo o null si el scope no está mapeado
     */
    public function moduleKeyForScope(string $scope): ?string
    {
        foreach ($this->modules as $key => $definition) {
            if (($definition['scope'] ?? null) === $scope) {
                return $key;
            }
        }

        return null;
    }

    // ── Rate limit ────────────────────────────────────────────────────────────

    /**
     * Devuelve el perfil de rate limit indicado desde la configuración.
     * Si el perfil no existe, retorna el perfil 'default'.
     *
     * @return array{rpm: int, burst: int}
     */
    public function rateLimitProfile(string $profile = 'default'): array
    {
        $profiles = config('integration_security.rate_limit_profiles', []);

        return $profiles[$profile] ?? $profiles['default'] ?? ['rpm' => 60, 'burst' => 10];
    }

    // ── Header names ─────────────────────────────────────────────────────────

    /**
     * Devuelve el nombre del header de autenticación indicado.
     *
     * @param string $key  'public_key' | 'timestamp' | 'nonce' | 'signature'
     */
    public function headerName(string $key): string
    {
        return config("integration_security.headers.{$key}", $key);
    }
}
