# Proyecto
Laravel 8 con Jetstream + Livewire + Tailwind.
Desarrollo en Laragon (Windows) y despliegue en hosting compartido.

# Reglas de arquitectura
- No cambiar nombres de propiedades Livewire existentes sin pedir confirmación.
- No mover lógica de negocio a Blade.
- Mantener validaciones en el componente o Form Request, no en JS.
- Preferir Eloquent claro antes que queries complejas si no hay problema de rendimiento.
- Respetar compatibilidad con Laravel 8.

# Reglas Blade / Livewire
- Usar wire:model, wire:model.defer o wire:click según corresponda.
- No inventar métodos Livewire inexistentes.
- Mantener modales Jetstream existentes salvo que se solicite refactor.
- Para responsive: usar patrón desktop table + mobile cards.
- No usar dropdowns dentro de contenedores con overflow-x-auto si pueden quedar recortados.
- En grids Tailwind, no usar grid-cols-12 sin col-span explícito.

# Reglas Tailwind
- Priorizar clases utilitarias simples.
- Evitar clases no estándar.
- Formularios: w-full, min-w-0 y break-words donde aplique.
- Modales: max-h con overflow-y-auto.

# Estilo de código
- Responder con cambios mínimos y seguros.
- Si una modificación impacta varios archivos, listar primero cuáles tocar.
- No reescribir módulos completos si solo se pidió ajuste visual.
