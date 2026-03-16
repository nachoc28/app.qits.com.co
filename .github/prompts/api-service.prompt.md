Create a Laravel service class for integrating with an external API in this project.

Project context:
- Laravel 8
- Shared hosting in production
- Jetstream + Livewire + Tailwind
- Prefer simple and maintainable architecture

Requirements:
- Create a service class under App\Services
- Use Laravel HTTP client when possible
- Add clear methods for authentication, request execution, and response parsing
- Handle errors safely using try/catch or throw() where appropriate
- Return normalized arrays or DTO-like structures when useful
- Do not use Laravel features introduced after version 8
- Keep secrets and credentials in .env and config files
- If token-based auth is required, explain where to store access token, refresh token, and expiry
- If pagination exists in the API, support it
- If rate limits may apply, mention it
- Keep the service reusable and testable

Output:
1. Files to create or modify
2. Suggested config keys and env variables
3. Final service class code
4. Example usage from controller, command, or Livewire component
5. Notes about error handling
