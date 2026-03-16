Create or refactor a Livewire component for this project using:

- Laravel 8
- Livewire
- Jetstream
- Tailwind CSS

Requirements:
- Do not rename existing public properties unless explicitly requested.
- Do not invent Blade methods that are not implemented in the component.
- Keep compatibility with Laravel 8.
- Prefer explicit action methods such as:
  - openToggle
  - confirmToggle
  - cancelDelete
  - openModal
  - closeModal
- Keep business logic in the component, not in Blade.
- Validation rules must be compatible with Laravel 8.
- If there is search with pagination, reset the page when the search term changes.
- If the component manages tables, support responsive UI with desktop table and mobile cards in the Blade.
- Keep code readable and avoid unnecessary refactors.

Output:
1. Files to create or modify
2. Public properties
3. Methods
4. Validation rules
5. Final PHP component code
6. If needed, mention the Blade file that should be updated
