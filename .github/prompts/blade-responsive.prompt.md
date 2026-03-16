Refactor or create a Blade view for this project using:

- Laravel 8
- Jetstream
- Livewire
- Tailwind CSS

Requirements:
- Keep existing Livewire bindings such as wire:model, wire:model.defer, wire:click, wire:submit.
- Do not rename existing Livewire public properties or methods.
- If the view contains a table, use this responsive pattern:
  - desktop table for sm and up
  - mobile cards for small screens
- Do not place dropdown menus inside containers with overflow-x-auto if they may get clipped.
- For forms, use:
  - w-full
  - min-w-0
  - break-words where needed
- For modals, use:
  - max height
  - overflow-y-auto
  - overflow-x-hidden
- Do not use invalid or non-standard Tailwind classes.
- If using grid layouts, do not use grid-cols-12 without explicit col-span classes.
- Keep changes minimal and safe.

Output:
1. Files to modify
2. Short explanation
3. Final Blade code
