# QITS - Project Architecture

**Last Updated:** 2026-07-14  
**Version:** 1.0  
**Stack:** Laravel 8, Jetstream, Livewire, Tailwind CSS, MySQL  

---

## Security Note - Testing Environment Files

- Real `.env.testing` files must not be committed.
- The repository provides `.env.testing.example` with empty secret values only.
- Test bootstrap prepares a non-secret runtime `APP_KEY` when `APP_ENV=testing`; the repository must not contain a real Laravel `APP_KEY` for tests.
- `.gitignore` blocks `.env.*` files by default and allows only safe examples such as `.env.example` and `.env.testing.example`.
- If a real key is ever committed, remove it from tracking immediately, rotate affected environments after confirming scope, and plan history cleanup separately.

## Security Note - APP_KEY Data Re-encryption

- Laravel encryption uses `config/app.php` cipher `AES-256-CBC`.
- The command `security:reencrypt-app-key-data` prepares encrypted database values for an `APP_KEY` rotation without changing `.env` or generating a key.
- The command is dry-run by default and requires `--apply` to persist changes.
- The new key must be supplied outside command history through one of:
  - temporary environment variable, default name `QITS_NEW_APP_KEY`
  - private file outside the repository via `--new-key-file`
- The source/decrypt key normally comes from the current runtime `APP_KEY`; rollback or advanced recovery can override it through:
  - temporary environment variable via `--source-key-env`
  - private file outside the repository via `--source-key-file`
- The command migrates only these known APP_KEY-encrypted values:
  - `form_notification_public_links.token_encrypted`
  - `empresa_whatsapp_settings.whatsapp_access_token`
  - `empresa_integrations.meta_json.google_refresh_token_encrypted`
- The command uses explicit Laravel encrypters for old/new keys and raw Query Builder updates. It intentionally avoids normal Eloquent assignment for `whatsapp_access_token` because that model has an `encrypted` cast tied to the currently active app key.
- Production execution requires `--confirm-production`; this is separate from `--apply`.
- Apply mode runs inside a single DB transaction and aborts on the first decrypt/encrypt/verification failure. It logs counts and technical table/id/field context only, never plaintext.
- Rollback strategy:
  - take a database backup before apply mode
  - do not activate the new `APP_KEY` until dry-run and apply counts are confirmed
  - if rollback is required before activation, restore the backup or run the inverse process with `--source-key-env` / `--source-key-file` pointing to the new key and `QITS_NEW_APP_KEY` pointing to the old key
  - after activation, keep the old key secured temporarily until post-rotation validation and rollback window close

## Content Management - Livewire Component Reactivity

- The operational article detail keeps separate Livewire 2 components for each flow step.
- Cross-step refresh uses Livewire 2 event listeners, not Livewire 3 attributes:
  - Step 1 emits `contentObjectiveUpdated` after Prompt 1 generation, refined-result save and objective ready transitions.
  - Step 2 listens through `$listeners` and re-renders from the database so availability reflects current refined fields, objective step status, `ready_at` and `ready_by`.
- Step 2 emits `contentDraftingUpdated` after Prompt 2 generation and drafting ready transitions.
- Step 3 listens through `$listeners` and re-renders from the database so its blocking state reflects current drafting state.
- Step cards render action success/error feedback inside the card where the action occurred, avoiding global alerts at the top of long scrolling views.
- The operational detail includes a sticky anchor stepper rendered by the parent Livewire component. It uses stable section IDs for Step 1, Step 2, Step 3, final files and delivery/publication; it does not behave as tabs and does not alter component state.

## AI Flows - Phase 1 Persistence

- `Flujos IA` is a new generic module namespace for configurable prompt-based processes.
- Phase 1 implementation state: persistence base, Eloquent models, relationships, labels, admin-only access service and structural tests.
- No visual UI, Livewire administration components, dynamic forms, prompt rendering, seeders for market research, OpenAI integration or migration from Content Management are included yet.
- MVP permissions are admin-only through the existing `User::isAdmin()` / `TipoUsuario` pattern:
  - create/edit flows: Administrador
  - execute flows: Administrador
  - consult strategic outputs: Administrador
- Tenant owner for executions and strategic outputs is `Empresa`.
- Access rules are centralized in `App\Services\AiFlows\AiFlowAccessService`.
- User-facing labels are centralized in `App\Support\AiFlowLabels`.
- Tables created:
  - `ai_flows`
  - `ai_flow_versions`
  - `ai_flow_steps`
  - `ai_flow_step_dependencies`
  - `ai_flow_variables`
  - `ai_flow_executions`
  - `ai_flow_execution_steps`
  - `ai_flow_execution_values`
  - `ai_flow_step_generations`
  - `ai_flow_step_results`
  - `ai_flow_strategic_outputs`
- Version states:
  - `draft`
  - `published`
  - `archived`
- Execution states:
  - `pending`
  - `in_progress`
  - `completed`
  - `cancelled`
- Execution step states:
  - `pending`
  - `in_progress`
  - `completed`
- `blocked` is intentionally not persisted in Phase 1; it remains a future visual/derived state from dependencies.
- Variable scopes:
  - `global`
  - `step`
  - `output`
- Variable input types:
  - `input`
  - `textarea`
- Strategic output types:
  - `strategic_report`
  - `executive_summary`
  - `current_strategic_base`
- Prompt and result bodies use `LONGTEXT`:
  - `ai_flow_steps.base_prompt`
  - `ai_flow_step_generations.final_prompt_text`
  - `ai_flow_step_results.result_text`
  - `ai_flow_strategic_outputs.content`
- Each prompt generation and result is historical and independent; Phase 1 does not overwrite previous generations or results.
- `ai_flow_step_generations.variables_snapshot_json` stores the future exact variable snapshot for prompt auditability.
- Variable names must be snake_case without spaces or accents and are unique per `ai_flow_version_id`.
- Step dependencies are persisted in `ai_flow_step_dependencies`; model-level validation prevents dependencies across different flow versions.
- The rule "one current strategic output per empresa + type" is enforced by `App\Services\AiFlows\AiFlowStrategicOutputService` inside a DB transaction, not by a MySQL partial unique index.

## AI Flows - Phase 2A Parser And Publication Rules

- `App\Services\AiFlows\AiFlowVariableParser` detects prompt placeholders using `{{nombre_variable}}`.
- Accepted variable names must match snake_case:
  - lowercase only
  - starts with a letter
  - numbers allowed after letters
  - underscores allowed
  - no spaces
  - no accents
  - no special characters
- The parser returns:
  - `variables`: valid unique variables in first-appearance order
  - `invalid_tokens`: invalid placeholder tokens detected in the prompt
- Duplicate valid variables are ignored after first appearance.
- Empty placeholders such as `{{}}` are treated as invalid tokens.
- `App\Services\AiFlows\AiFlowVersionValidationService` validates whether a version can be published.
- Publication validation rules implemented:
  - version must be `draft`
  - version must have at least one active step
  - every active step must have non-empty `base_prompt`
  - every valid variable detected in active prompts must exist in `ai_flow_variables`
  - configured variable names must remain valid
  - configured variables no longer used in active prompts return warnings, not errors
  - invalid prompt tokens return errors
  - output variables require `source_step_id`
  - output `source_step_id` must belong to the same version
  - variable step associations must belong to the same version
  - step dependencies must reference steps from the same version
- `App\Services\AiFlows\AiFlowVersionService` publishes a valid draft version inside a DB transaction.
- Publication behavior:
  - validation errors abort publication
  - selected version becomes `published`
  - `published_at` and `published_by` are recorded
  - previous `published` versions of the same flow are moved to `archived`
  - only one `published` version remains per flow after successful publication
- A basic edit-protection method prevents editing a published version that already has historical executions.

## AI Flows - Phase 2B Admin UI

- Current admin UI scope is intentionally minimal and does not include variable configuration, dynamic forms, client executions or prompt rendering for executions.
- Routes added:
  - `GET /admin/ai-flows` named `admin.ai-flows.index`
  - `GET /admin/ai-flows/create` named `admin.ai-flows.create`
  - `GET /admin/ai-flows/{flow}/edit` named `admin.ai-flows.edit`
  - `GET /admin/ai-flows/{flow}/versions` named `admin.ai-flows.versions.index`
  - `GET /admin/ai-flows/{flow}/versions/{version}` named `admin.ai-flows.versions.show`
- Admin-only access is enforced in routes and Livewire components through the existing `User::isAdmin()` pattern.
- Livewire 2 components added:
  - `App\Http\Livewire\Admin\AiFlows\AiFlowIndex`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowForm`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowVersionIndex`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow`
- Blade wrapper views added under:
  - `resources/views/admin/ai-flows/`
- Livewire views added under:
  - `resources/views/livewire/admin/ai-flows/`
- Available screens:
  - flow listing with active state, key, category placeholder and current published version
  - create flow form
  - edit flow form
  - version listing with Spanish status labels and draft creation
  - version detail with existing step list and publish action
- Flow creation validates:
  - required `name`
  - required unique `key`
  - key without spaces or accents, using lowercase letters, numbers, underscore or hyphen
- Flow editing currently allows:
  - `name`
  - `description`
  - `is_active`
- Version creation uses the next incremental `version_number`; first version is `1`.
- Publication actions call `App\Services\AiFlows\AiFlowVersionService`; controllers/routes do not publish directly.
- Publication validation errors and warnings are shown on screen in Spanish.
- Navigation now includes `Flujos IA` in desktop and responsive admin navigation with active state covering `admin.ai-flows*`, `admin.ai-flow-executions*` and `admin.ai-flow-strategic-outputs*`.

## AI Flows - Phase 2C Basic Step Builder

- The version detail screen now includes a basic step builder inside `App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow`.
- Scope remains limited to draft version structure; it does not configure variables, execute flows, render final execution prompts, save GPT results or seed the market research flow.
- Administrador-only access continues through the existing route middleware and Livewire checks.
- Draft versions can create and edit steps with:
  - `step_key`
  - `name`
  - `description`
  - `position`
  - `recommended_gpt`
  - `expected_output_name`
  - `base_prompt`
  - `is_active`
- `step_key` is unique per version and must use lowercase letters, numbers, underscore or hyphen, without spaces or accents.
- Step `position` is required and unique per version.
- Published or archived versions cannot create, edit or activate/inactivate steps from the UI.
- Steps are not physically deleted in this phase; draft versions can only toggle `is_active`.
- The step list is ordered by `position` and shows:
  - position
  - name
  - step key
  - recommended GPT
  - expected output
  - count of valid variables detected in `base_prompt`
  - count of invalid placeholder tokens detected in `base_prompt`
  - active/inactive state
- Prompt preview uses `App\Services\AiFlows\AiFlowVariableParser`.
- The preview only detects variables and invalid tokens; it does not create or update `ai_flow_variables`.
- The UI supports one optional explicit dependency per step through `depends_on_step_id`.
- Dependency rules in this phase:
  - dependency must reference a step from the same flow version
  - dependency must reference an earlier position
  - dependency cannot reference the same step
  - no dependency creates no explicit row; future execution can still derive sequential dependency by order
- Explicit dependencies are stored in `ai_flow_step_dependencies`, preserving the table's future support for multiple dependencies.
- Publication still uses `App\Services\AiFlows\AiFlowVersionService`; versions with detected variables that are not configured in `ai_flow_variables` fail publication as designed.

## AI Flows - Phase 2D Variable Configuration

- The version detail screen now includes a `Variables del flujo` section inside `App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow`.
- Scope remains limited to variable synchronization/configuration for draft versions; it does not execute flows, render final execution prompts, copy prompts, save GPT results or seed the market research flow.
- Variable synchronization behavior:
  - parses all active step prompts in version order using `App\Services\AiFlows\AiFlowVariableParser`
  - creates missing `ai_flow_variables` for valid detected placeholders
  - does not delete existing variables
  - does not duplicate variables already configured for the version
  - visually marks configured variables that no longer appear in active prompts as `No usada`
  - displays invalid placeholder tokens found in active prompts
- Variable names are not editable from the UI; they come from detected placeholders.
- New variables are created with suggested defaults:
  - `label`: generated from the variable name by replacing `_` with spaces and capitalizing the first letter
  - `scope`: `global`
  - `is_required`: `true`
  - `position`: first-appearance order in active prompts
  - `input_type`: `textarea` when the variable name contains one of the long-text hints; otherwise `input`
- Long-text input hints currently include:
  - `objetivo`
  - `observaciones`
  - `descripcion`
  - `publico`
  - `servicios`
  - `competidores`
  - `canales`
  - `restricciones`
  - `temporadas`
  - `brief`
  - `informacion`
  - `sitemap`
  - `contexto`
- Draft versions can edit:
  - `label`
  - `input_type`
  - `scope`
  - `is_required`
  - `help_text`
  - `placeholder`
  - `default_value`
  - `position`
  - `ai_flow_step_id` for `step` scope
  - `source_step_id` for `output` scope
- Published or archived versions render variable configuration as read-only and block synchronization/edit actions.
- Scope validation rules:
  - `global` clears/ignores `ai_flow_step_id` and `source_step_id`
  - `step` requires `ai_flow_step_id` from the same version
  - `output` requires `source_step_id` from the same version
- `App\Models\AiFlowVariable` enforces structural integrity for variable names and same-version step references.
- Publication remains delegated to `App\Services\AiFlows\AiFlowVersionService`; after variables are synchronized/configured and validation passes, a draft version can be published.

## AI Flows - Phase 3A Base Executions

- The module now supports creating base executions of a published AI flow for an `Empresa`.
- Scope remains limited to execution creation and read-only progress visualization; it does not include dynamic variable forms, final prompt rendering, prompt copy actions, GPT result saving, strategic outputs, market research seeders or Content Management migration.
- Routes added:
  - `GET /admin/ai-flow-executions` named `admin.ai-flow-executions.index`
  - `GET /admin/ai-flow-executions/create` named `admin.ai-flow-executions.create`
  - `GET /admin/ai-flow-executions/{execution}` named `admin.ai-flow-executions.show`
- Livewire 2 components added:
  - `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionIndex`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionForm`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow`
- Blade wrapper views added under:
  - `resources/views/admin/ai-flows/executions/`
- Livewire views added under:
  - `resources/views/livewire/admin/ai-flows/`
- `App\Services\AiFlows\AiFlowExecutionService` centralizes:
  - resolving the current published version for an active flow
  - creating executions inside a DB transaction
  - initializing execution steps for active version steps
  - calculating derived visual step status including `Bloqueada`
- Execution creation rules:
  - admin-only through `AiFlowAccessService`
  - `empresa_id` is required and validated
  - flow is required
  - only active flows with a published version can be selected from the UI
  - direct attempts to create an execution for a flow without a published version are rejected
  - `title` is required
- Execution initialization:
  - creates `ai_flow_executions` with `status = in_progress`
  - stores `empresa_id`, `ai_flow_id`, and the current published `ai_flow_version_id`
  - stores `started_by` and `started_at`
  - creates one `ai_flow_execution_steps` row for each active step in the published version
  - each execution step starts as `pending`
  - inactive version steps are not initialized
- Executions are frozen to the `ai_flow_version_id` used at creation time; later published versions do not rewrite existing executions.
- The detail screen shows base progress:
  - empresa
  - flow
  - version
  - title
  - general status
  - completed/total step count
  - ordered stage cards
- Visual step statuses:
  - `Pendiente`
  - `Bloqueada`
  - `En proceso`
  - `Completada`
- `blocked` is not persisted. It is derived at runtime:
  - if a step has explicit dependencies, all dependency execution steps must be completed
  - if a step has no explicit dependencies, sequential dependency by position applies
  - the first step without explicit dependencies is available as `Pendiente`
- Navigation now exposes the executions area from the AI Flows module while keeping existing Content Management navigation unchanged.

## AI Flows - Phase 3B Dynamic Variables And Prompt Rendering

- The execution detail now supports filling variables for available stages and generating final prompts.
- Scope remains limited to variable value entry, exact placeholder replacement and generation history; it does not include advanced copy tracking, GPT result saving, stage completion, strategic outputs, market research seeders or Content Management migration.
- `App\Services\AiFlows\AiFlowPromptRenderService` renders and persists final prompts.
- Prompt rendering rules:
  - uses the `base_prompt` of the execution step's frozen `AiFlowStep`
  - parses placeholders with `App\Services\AiFlows\AiFlowVariableParser`
  - replaces only configured `{{variable}}` placeholders
  - preserves textarea line breaks in inserted values
  - does not alter prompt instructions beyond exact replacement
  - does not invent missing data
  - does not call AI or external services
- Prompt generation persists one historical row per click in `ai_flow_step_generations`.
- Each generation stores:
  - `ai_flow_execution_step_id`
  - `ai_flow_step_id`
  - `final_prompt_text`
  - `variables_snapshot_json`
  - `generated_by`
  - `generated_at`
- `variables_snapshot_json` includes variable name, label, scope, source and value used at generation time.
- Variable value resolution:
  - `global`: value stored for the execution with `ai_flow_execution_step_id = null`
  - `step`: value stored for the current execution step
  - `output`: latest result from the configured source step; in this phase it is prepared and blocks generation when no source result exists
- Prompt generation fails with a controlled Spanish message when:
  - a required variable is empty
  - a placeholder is not configured
  - a configured output variable has no available source result
  - the prompt contains invalid placeholder tokens
- On successful generation, an execution step moves from `pending` to `in_progress`.
- Regenerating creates a new historical generation and does not overwrite previous prompts.
- The execution detail UI now renders dynamic fields per available step:
  - `input` variables render as text inputs
  - `textarea` variables render as textareas
  - `help_text`, `placeholder` and `default_value` are shown/applied where configured
  - output variables are shown as source notices, not editable fields
- Variable values are saved in `ai_flow_execution_values` with `filled_by` and `filled_at`.
- Saving values updates existing rows for the same execution, variable and scope-specific execution step instead of creating duplicate logical values.
- Blocked stages keep the read-only blocked message and do not render editable forms.
- Each step card shows local success/error feedback, latest generated prompt and a native collapsible generation history.

## AI Flows - Phase 3C GPT Results And Step Completion

- The execution detail now supports the manual operator loop after prompt generation:
  - copy the latest generated prompt
  - paste an external GPT result
  - save the result historically
  - mark the stage as completed
- Scope remains limited to manual GPT result handling and stage completion; it does not include current strategic outputs, market research seeders, Content Management migration, OpenAI integration or automatic file generation.
- Copy behavior:
  - the latest generated prompt shows a `Copiar prompt` button
  - it uses Alpine and `navigator.clipboard` when available
  - if clipboard API is unavailable, the readonly textarea remains selectable
  - copy feedback is local to the stage card
  - copy does not write to the database
- `App\Services\AiFlows\AiFlowStepResultService` centralizes GPT result persistence.
- Result saving rules:
  - requires non-empty result text
  - requires at least one prompt generation for the stage
  - associates the result to the latest `ai_flow_step_generations` row
  - stores `ai_flow_execution_step_id`, `ai_flow_step_generation_id`, `result_text`, `saved_by`, and `saved_at`
  - allows multiple historical results per stage
- The execution detail shows:
  - latest saved result
  - collapsible result history
  - local success/error messages per stage
- `App\Services\AiFlows\AiFlowStepCompletionService` centralizes stage completion.
- Stage completion rules:
  - requires at least one saved result
  - rejects blocked stages
  - updates `ai_flow_execution_steps.status = completed`
  - stores `completed_by` and `completed_at`
  - runs inside a transaction
- Execution completion rule:
  - when all execution steps are completed, `ai_flow_executions.status` becomes `completed`
  - `ai_flow_executions.completed_at` is set
- Unlocking remains derived and not persisted:
  - after a stage is completed, subsequent stage availability is recalculated from dependencies/sequence
  - `blocked` is still a visual state only
- Output variables now complete the Phase 3B preparation:
  - `AiFlowPromptRenderService` resolves `output` variables from the latest saved result of the configured source step inside the same execution
  - if no source result exists, generation fails with a clear controlled message

## AI Flows - Phase 4 Strategic Outputs

- The execution detail now allows marking a saved stage result as a reusable strategic output for the client.
- Scope remains limited to manual strategic-output marking and consultation; it does not include market research seeders, Content Management migration, OpenAI integration, file uploads or automatic document generation.
- `App\Services\AiFlows\AiFlowStrategicOutputService` centralizes strategic-output marking.
- Strategic output marking rules:
  - only results from completed execution stages can be marked
  - empty results are rejected
  - supported types are `strategic_report`, `executive_summary` and `current_strategic_base`
  - each output stores empresa, execution, execution step, source result, type, title, content, current flag, marking user and marking date
  - source result content is copied into `ai_flow_strategic_outputs.content` for historical reuse
- Current-output rule:
  - a new marked output becomes `is_current = true`
  - previous current outputs for the same `empresa_id + type` are set to `is_current = false`
  - outputs from other companies or other types are not affected
  - this is handled in a DB transaction, not through a partial SQL index
- Routes added:
  - `GET /admin/ai-flow-strategic-outputs` named `admin.ai-flow-strategic-outputs.index`
  - `GET /admin/ai-flow-strategic-outputs/{output}` named `admin.ai-flow-strategic-outputs.show`
- Livewire 2 component added:
  - `App\Http\Livewire\Admin\AiFlows\AiFlowStrategicOutputIndex`
- Blade views added under:
  - `resources/views/admin/ai-flows/strategic-outputs/`
- Strategic-output screens show:
  - company
  - type label
  - title
  - flow and execution origin
  - source stage
  - marking user/date
  - current status
  - full content detail
- Access remains MVP admin-only through `AiFlowAccessService`.

## 0. System Snapshot (AI Context)

**Current State (as of 2026-07-09):**

This document reflects the **REAL, VALIDATED** state of the QITS system. All statements below are verified against actual code in:
- `app/Http/Requests/Api/Seo/UtmConversionIngestRequest.php`
- `app/Services/Seo/UtmConversionIngestService.php`
- `app/Models/SeoUtmConversion.php`
- `database/migrations/2026_03_28_120000_add_source_record_fields_to_seo_utm_conversions_table.php`
- `app/Http/Controllers/Api/Seo/UtmConversionIngestController.php`

### SEO UTM Ingestion (Primary Integration)

**Incoming Request Flow:**
```
External System (WordPress UTM Tracker)
  ↓ POST /api/seo/utm-conversions + HMAC-SHA256 signature
  ↓
Middleware: integration.auth validates signature
  ↓
FormRequest: UtmConversionIngestRequest validates field types
  ↓
Service: UtmConversionIngestService normalizes + idempotency check
  ↓
Database: INSERT with UNIQUE(empresa_id, source_system, source_record_id)
  ↓
Response: 201 (new) or 200 (duplicate) with result tuple
```

**Idempotency Guarantee:**
- Duplicate detection via database constraint: `UNIQUE(empresa_id, source_system, source_record_id)`
- On duplicate: `QueryException` (MySQL 1062) caught, existing record retrieved, `created=false` returned
- Response: **200 OK** (not 201) signals idempotent duplicate to client
- `source_system` always set server-side to `'wordpress_utm_tracker'` (client cannot override)

**Field Validation Rules:**
- **2 REQUIRED fields:** `conversion_datetime`, `source_record_id`
- **9 OPTIONAL fields:** `page_url`, `form_name`, `source`, `medium`, `campaign`, `term`, `content`, `event_name`, `lead_id`, `raw_payload_json`
- **ALL OTHER FIELDS ARE SILENTLY IGNORED** (no error, no persistence)
- `source_record_id` MUST be string type (not numeric); auto-cast in `prepareForValidation()` if sent as integer from legacy clients

**Response Codes:**
- `201 Created` — New conversion persisted, `created=true`
- `200 OK` — Idempotent duplicate, `created=false` (no error)
- `422 Unprocessable Entity` — Validation failed, includes `error_code`, `request_id`, `failed_fields`, `debug.received_fields`
- `403 Forbidden` — HMAC signature mismatch
- `500 Internal Server Error` — Unhandled exception

---

## 0.1 Active API Contract - UTM Ingestion (Source of Truth)

**Endpoint:** `POST /api/seo/utm-conversions`  
**Authentication:** HMAC-SHA256 signature in `X-Signature` header  
**Content-Type:** `application/json`

### Request Payload Schema

```json
{
  "conversion_datetime": "2026-03-28T10:30:00",  // REQUIRED: ISO 8601 or parseable date
  "source_record_id": "12345",                   // REQUIRED: string, max 191 chars
  "page_url": "https://example.com/contact",    // optional: valid URL, max 500
  "form_name": "Contact Form",                  // optional: string, max 150
  "source": "google",                           // optional: string, max 120
  "medium": "organic",                          // optional: string, max 120
  "campaign": "summer_2026",                    // optional: string, max 150
  "term": "marketing platform",                 // optional: string, max 150
  "content": "banner_a",                        // optional: string, max 150
  "event_name": "generate_lead",                // optional: string, max 120
  "lead_id": 42,                                // optional: positive integer (0 = null)
  "raw_payload_json": {                         // optional: any JSON object
    "event_type": "click",
    "utm_source": "google",
    "extra": {}
  }
}
```

### Validation Rules (Authoritative)

| Field | Rule | Response on Fail |
|-------|------|-----------------|
| `conversion_datetime` | required, date | 422: "conversion_datetime es requerido." or "conversion_datetime debe ser una fecha válida." |
| `source_record_id` | required, string, max:191 | 422: "source_record_id es requerido." or "source_record_id debe ser texto." |
| `page_url` | nullable, url, max:500 | 422: "page_url debe ser una URL válida." |
| `form_name` | nullable, string, max:150 | 422 (max violated) |
| `source` | nullable, string, max:120 | 422 (max violated) |
| `medium` | nullable, string, max:120 | 422 (max violated) |
| `campaign` | nullable, string, max:150 | 422 (max violated) |
| `term` | nullable, string, max:150 | 422 (max violated) |
| `content` | nullable, string, max:150 | 422 (max violated) |
| `event_name` | nullable, string, max:120 | 422: "event_name no debe superar 120 caracteres." |
| `lead_id` | nullable, integer, min:1 | 422 (integer/min violation) |
| `raw_payload_json` | nullable, array | 422: "raw_payload_json debe ser un objeto/array JSON válido." |

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Conversión UTM registrada correctamente.",
  "data": {
    "id": 1,
    "empresa_id": 10,
    "conversion_datetime": "2026-03-28 10:30:00",
    "source_record_id": "12345",
    "created": true
  }
}
```

### Idempotent Response (200 OK - on duplicate)

```json
{
  "success": true,
  "message": "Conversión UTM ya existente (idempotente).",
  "data": {
    "id": 1,
    "empresa_id": 10,
    "conversion_datetime": "2026-03-28 10:30:00",
    "source_record_id": "12345",
    "created": false
  }
}
```

### Validation Error Response (422)

```json
{
  "success": false,
  "message": "El payload de conversión UTM no es válido.",
  "error_code": "VALIDATION_FAILED",
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "errors": {
    "source_record_id": ["source_record_id debe ser texto."]
  },
  "failed_fields": ["source_record_id"],
  "debug": {
    "received_fields": ["source_record_id", "conversion_datetime", "utm_source"]
  }
}
```

### Fields NOT in Contract (Silently Ignored)

These fields are **accepted but NOT persisted** or **validated**:
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` (use `source`, `medium`, `campaign`, `term`, `content` instead)
- `event_type` (use `event_name` instead)
- `referrer`, `user_agent`, `ip_address`, `extra`, `created_at`, or any undeclared field
- Numbers sent as `source_record_id` will be auto-cast to string (legacy compatibility)

### Database Storage

**Table:** `seo_utm_conversions`

| Column | Type | Indexed | Notes |
|--------|------|---------|-------|
| id | BIGINT | PK | Auto-increment |
| empresa_id | BIGINT | FK | Row-level security |
| conversion_datetime | DATETIME | — | Parsed from ISO 8601 or date string |
| source_record_id | VARCHAR(191) | UNIQUE (part of composite) | Idempotency key |
| source_system | VARCHAR(32) | UNIQUE (part of composite) | Always `'wordpress_utm_tracker'` (server-set) |
| page_url | VARCHAR(500) | — | Nullable |
| form_name | VARCHAR(150) | — | Nullable |
| source, medium, campaign, term, content | VARCHAR(120-150) | — | Nullable |
| event_name | VARCHAR(120) | — | Nullable |
| lead_id | BIGINT | FK | Nullable; 0 normalized to NULL |
| raw_payload_json | JSON | — | Full original payload (audit trail) |
| created_at, updated_at | DATETIME | — | Timestamps |

**Unique Constraint:** `UNIQUE(empresa_id, source_system, source_record_id)` named `seo_utm_conv_source_unique`

---

## 0.2 Rules (Strict AI Guidance)

These rules are NON-NEGOTIABLE for working with SEO UTM ingestion:

### DO:
- ✅ **Validate `source_record_id` as string** before POSTing (cast numeric IDs to string)
- ✅ **Include `conversion_datetime`** in every payload (required field)
- ✅ **Use canonical field names:** `source`, `medium`, `campaign`, `term`, `content` (NOT `utm_*` variants)
- ✅ **Use `event_name`** for event classification (NOT `event_type`)
- ✅ **Wrap sensitive or complex original payloads in `raw_payload_json`** for audit trail
- ✅ **Expect 200 OK on idempotent resubmission** — treat as success, not error
- ✅ **Include `X-Request-Id` header** (or service generates UUID) for distributed tracing
- ✅ **Sign requests with HMAC-SHA256** using shared secret (`config/integration_security.php`)
- ✅ **Handle both 201 and 200 responses** in client code (check `created` field in response data)
- ✅ **Retry on 5xx with exponential backoff** (idempotency guarantees safety)

### DO NOT:
- ❌ **Send `source_record_id` as numeric type** (causes 422 "must be a string")
- ❌ **Use `utm_source`, `utm_medium`, etc.** (silently ignored; use `source`, `medium`, etc.)
- ❌ **Send `event_type`** (not recognized; use `event_name`)
- ❌ **Omit `conversion_datetime`** or send invalid date (causes 422)
- ❌ **Leave `source_record_id` empty or null** (causes 422)
- ❌ **Assume 422 errors are fatal**—inspect `failed_fields` and retry with corrected payload
- ❌ **Send `source_system`** in payload (always set server-side to `'wordpress_utm_tracker'`)
- ❌ **Manually generate unique IDs for idempotency**—use deterministic, externally-tracked IDs from source system
- ❌ **Polish payloads by adding extra fields** expecting them to persist (they will be silently dropped)
- ❌ **Parse `source_system` from response**—it is always `'wordpress_utm_tracker'` for this endpoint

### Data Type Strictness:
- `source_record_id`: MUST be string (even if source ID is numeric; cast to string)
- `conversion_datetime`: MUST be ISO 8601 or parseable date string (Carbon::parse() compatible)
- `lead_id`: MUST be positive integer (0 or null → normalized to NULL)
- `page_url`: MUST be valid URL (checked with Laravel `url` validator)
- All text fields: Truncated to max length in database (no error thrown); strings longer than limit are silently cut

---

## 0.3 Deprecated / Legacy Behavior

### Temporarily Supported (Will Be Removed)

**Field: `id` (legacy `source_record_id`)**
- **Status:** Deprecated but still accepted
- **Behavior:** If `source_record_id` is empty AND `id` is provided, `id` is auto-cast to string and used as `source_record_id`
- **Code Location:** `UtmConversionIngestRequest::prepareForValidation()` line 31-35
- **Migration Path:** Update WordPress plugin to send `source_record_id` directly
- **Removal Timeline:** March 2026 end-of-quarter (Q2 2026 planning)

**String JSON in `raw_payload_json`**
- **Status:** Accepted but should be sent as object/array
- **Behavior:** If `raw_payload_json` is a JSON string, it is decoded automatically before validation
- **Code Location:** `UtmConversionIngestRequest::prepareForValidation()` line 19-28
- **Migration Path:** Send `raw_payload_json` as JSON object, not string
- **Removal Timeline:** No immediate removal planned; kept for compatibility with older plugins

### No Longer Supported (Removed)

- Direct `utm_*` field mapping (users should map to `source`/`medium`/`campaign` before sending)
- `event_type` as alias for `event_name` (strict field naming since 2026-03-28)
- Implicit empresa resolution (now requires HMAC signature + middleware context)

### Data Persistence (Silent Drops)

These fields are **accepted in request but NOT persisted** to database:
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`—wrap in `raw_payload_json` if needed for audit
- `event_type`—remap to `event_name`
- `referrer`, `user_agent`, `ip_address`, `device_type`—add to `raw_payload_json` if needed
- `visitor_id`, `session_id`, `extra`—store in `raw_payload_json`
- Any field not in the official API contract

**Rationale:** Keeps database schema stable; audit trail preserved in `raw_payload_json`

---

## 1. Project Overview

QITS is a comprehensive lead management and integration platform built on Laravel 8. It provides:
- Lead capture and management
- Multi-channel integrations (WhatsApp, Google Ads, UTM tracking)
- Content management with tenant-scoped imports, article lifecycle, prompt generation traceability and versioned final files
- Real-time event ingestion and processing
- API-first architecture for third-party integrations
- Authentication via Jetstream/Fortify and authorization through `TipoUsuario`, `User::isAdmin()` and module-specific access checks

**Primary Users:** Marketing agencies, sales teams, system administrators  
**Deployment:** Laragon (development), Shared hosting (production)  

---

## 2. Technology Stack

### Backend
- **Framework:** Laravel 8.x
- **Authentication:** Jetstream + Sanctum
- **ORM:** Eloquent
- **Database:** MySQL 5.7+
- **PHP:** 8.1 (current runtime target)
- **Spreadsheet Parsing:** PhpSpreadsheet 1.30.x (`phpoffice/phpspreadsheet`)
- **Background Jobs:** Laravel Queue (configuration via `config/queue.php`)

### Frontend
- **UI Framework:** Livewire (live components)
- **CSS:** Tailwind CSS 3.4.x
- **Blade Templates:** Server-side rendering
- **JavaScript:** Minimal vanilla JS + Livewire lifecycle

### Integrations
- **Google Ads API:** OAuth 2.0 via `config/google.php`
- **WhatsApp Hub:** Custom webhook handlers
- **WordPress UTM Tracker:** REST API integration
- **External APIs:** Signature-based authentication (HMAC-SHA256)

---

## 3. Folder Structure & Responsibilities

### `/app`
```
├── Actions/                 # Permission-based action classes
│   ├── Fortify/            # Auth actions (login, register, etc.)
│   └── Jetstream/          # Profile actions
├── Console/
│   ├── Commands/           # Artisan commands
│   └── Kernel.php          # Scheduled tasks
├── Exceptions/
│   ├── Handler.php         # Global exception handler
│   ├── Google/             # Google API exceptions
│   ├── IntegrationSecurity/# Auth/signature exceptions
│   ├── Seo/                # SEO module exceptions
│   └── WhatsAppHub/        # WhatsApp exceptions
├── Http/
│   ├── Controllers/        # API & web controllers
│   ├── Livewire/           # Livewire components
│   ├── Middleware/         # Route middleware
│   │   └── integration.auth# Signature-based auth for APIs
│   └── Requests/
│       └── Api/            # Form request validation
│           ├── Seo/
│           └── WhatsAppHub/
├── Jobs/                   # Queued jobs
│   ├── Seo/
│   └── WhatsAppHub/
├── Models/                 # Eloquent models
│   ├── Empresa.php         # Company entity
│   ├── Lead.php            # Lead records
│   ├── SeoUtmConversion.php # UTM tracking
│   ├── EmpresaWhatsAppSetting.php
│   ├── EmpresaSeoProperty.php
│   └── ...others
├── Providers/              # Service providers
├── Services/               # Business logic layer
│   ├── Seo/UtmConversionIngestService.php
│   ├── WhatsAppHub/
│   └── ...others
└── Support/                # Helper classes
```

### `/routes`
```
├── web.php        # Web routes (Livewire, views)
├── api.php        # REST API routes
├── channels.php   # Broadcasting channels
└── console.php    # Artisan commands
```

### `/config`
- **Key files:**
  - `app.php` — Application name, timezone, providers
  - `auth.php` — Auth guards and providers
  - `database.php` — Database connection
  - `google.php` — Google Ads API credentials
  - `integration_security.php` — HMAC signature config
  - `seo.php` — SEO module configuration
  - `whatsapp_hub.php` — WhatsApp Hub settings

### `/database`
```
├── migrations/    # Schema changes (timestamped)
├── factories/     # Model factories for testing
└── seeders/       # Database seeders
```

### `/resources`
```
├── views/         # Blade templates
├── css/           # Tailwind CSS
└── js/            # Frontend JS
```

### `/DESIGN_DOCS`
- `csv_utm_import_contract.md` — CSV import specification
- `duplicate_detection_analysis.md` — Deduplication strategy

---

## 4. Architectural Patterns

### 4.1 Service Layer Pattern
**Purpose:** Encapsulate business logic away from controllers  
**Location:** `app/Services/`

**Example:** SEO UTM Ingestion
```
WordPress Plugin
    ↓ (HTTP POST with HMAC signature)
Middleware (integration.auth:seo.utm_conversions_ingest)
    ↓ (Validates signature)
FormRequest (UtmConversionIngestRequest)
    ↓ (Validates field types, required rules)
Controller (UtmConversionIngestController)
    ↓ (Orchestrates flow)
Service (UtmConversionIngestService)
    ↓ (Normalizes, validates, persists with idempotency)
Model (SeoUtmConversion)
    ↓ (Persists to database)
```

### 4.2 Idempotent Ingestion Pattern
**Problem:** Prevent duplicate records on plugin retry/resubmission  
**Solution:** Unique database constraint + QueryException catch

**Mechanism:**
```
1. Database constraint: UNIQUE(empresa_id, source_system, source_record_id)
2. Service attempts: Model::create($data)
3. If duplicate key error (MySQL 1062):
   - Catch QueryException
   - Retrieve existing record via source_record_id
   - Return existing record with 'created=false' flag
4. Controller returns:
   - 201 Created: for new records
   - 200 OK: for idempotent duplicates
```

### 4.3 Form Request Validation with Preprocessing
**Purpose:** Validate input and transform field names before service layer  
**Location:** `app/Http/Requests/Api/Seo/`

**Lifecycle:**
```
Raw Payload (JSON)
    ↓
prepareForValidation() — Decode JSON, map legacy fields
    ↓
rules() — Define validation rules
    ↓
failedValidation() — Custom error response with observability fields
    ↓
Validated Data
```

**Enhanced Error Response (422):**
```json
{
  "error_code": "VALIDATION_ERROR",
  "message": "The given data was invalid.",
  "request_id": "550e8400-e29b-41d4-a716-446655440000",
  "failed_fields": ["source_record_id"],
  "errors": {
    "source_record_id": ["The source_record_id must be a string."]
  },
  "debug": {
    "received_fields": ["source_record_id": 12345]
  }
}
```

### 4.4 Integration Security (Signature-Based Auth)
**Purpose:** Verify requests from trusted external systems  
**Location:** `app/Http/Middleware/integration.auth`

**Flow:**
```
Request Header: X-Signature (HMAC-SHA256)
    ↓
Middleware calculates: HMAC-SHA256(payload, shared_secret)
    ↓
Compares: calculated === received
    ↓
If mismatch → 403 Forbidden
If match → Inject integration_id into request->attributes
    ↓
Gate check: integration.auth:seo.utm_conversions_ingest
```

### 4.5 Eloquent Model Pattern
**Purpose:** Type-safe database access with eager loading  
**Location:** `app/Models/`

**Features:**
- `fillable` arrays → Explicit mass assignment protection
- `casts` → Automatic type conversion (datetime, int, json, etc.)
- `belongsTo`, `hasMany` relationships → Eloquent eager loading
- Computed properties for server-side field assignment

---

## 5. Key Modules

### 5.1 SEO UTM Conversion Ingestion
**Objective:** Ingest UTM tracking events from external plugins in real-time  
**Status:** Active (idempotency refactored 2026-03-28)

**Components:**
- **Model:** `SeoUtmConversion` — Tracks conversion_datetime, source_system, source_record_id, source/medium/campaign/term/content
- **Service:** `UtmConversionIngestService` — Validates, normalizes, handles idempotency
- **FormRequest:** `UtmConversionIngestRequest` — Validates input, maps legacy fields, enhanced 422 observability
- **Controller:** `UtmConversionIngestController` — HTTP endpoint, returns created/idempotent response
- **Route:** `POST /api/seo/utm-conversions` (protected by `integration.auth:seo.utm_conversions_ingest`)

**Database Table:** `seo_utm_conversions`
```sql
CREATE UNIQUE INDEX utc_empresa_source_record ON seo_utm_conversions(empresa_id, source_system, source_record_id);
```

**Idempotency Key:** `(empresa_id, source_system, source_record_id)`

### 5.2 WhatsApp Hub
**Objective:** Integrate WhatsApp messaging into the lead management system  
**Status:** Active

**Components:**
- **Configuration:** `config/whatsapp_hub.php`
- **Exceptions:** `app/Exceptions/WhatsAppHub/`
- **Jobs:** `app/Jobs/WhatsAppHub/`
- **Services:** `app/Services/WhatsAppHub/`

### 5.3 Google Ads Integration
**Objective:** OAuth 2.0 integration with Google Ads for lead attribution  
**Status:** Active

**Components:**
- **Configuration:** `config/google.php`
- **Exceptions:** `app/Exceptions/Google/`
- **Services:** `app/Services/Google/`

### 5.4 Integration Security
**Objective:** Centralized authentication for third-party API consumers  
**Status:** Active

**Components:**
- **Configuration:** `config/integration_security.php`
- **Middleware:** `app/Http/Middleware/integration.auth`
- **Exceptions:** `app/Exceptions/IntegrationSecurity/`
- **Model:** `IntegrationSecurityLog` — Audit trail

### 5.5 WordPress Form Notifications via WhatsApp (MVP)
**Objective:** Receive WordPress form submissions from AG UTM Tracker (WPForms and Elementor Pro Forms) and notify the configured WhatsApp attention number per company using approved template messages.  
**Status:** MVP Implemented (production flow active with integration security, idempotency, anti-abuse and secure public link)

**Functional Flow:**
```
WordPress form submit
  ↓
AG UTM Tracker plugin
  ↓ POST /api/wordpress/form-notifications (signed JSON)
Middleware: integration.auth validates signature + scope + active company service
  ↓
FormRequest: WordpressFormNotificationIngestRequest validates payload
  ↓
Controller: WordpressFormNotificationIngestController
  ↓
Service: WordpressFormNotificationIngestService
  - idempotency check (empresa_id, source_system, source_record_id)
  - service gate check: formularios-whatsapp-api
  - destination_phone + opt-in checks
  - template status gate (approved|pending|disabled)
  - anti-abuse rate limit checks (minute/hour/day)
  - secure public link creation (token_hash + token_encrypted)
  - enqueue DispatchWordpressFormNotificationJob only when queued
  ↓
Job: DispatchWordpressFormNotificationJob
  - validates queued window and link validity
  - reconstructs URL button parameter from token_encrypted
  - sends WhatsApp template through WhatsAppApiClient::sendTemplate() using global QITS sender credentials
  - updates lifecycle status and provider_response_json
  ↓
End user opens GET /s/{token}
```

**Endpoint & Security:**
- **Endpoint:** `POST /api/wordpress/form-notifications`
- **Middleware:** `integration.auth`
- **Scope:** `wordpress.form_notifications_ingest`
- **Headers required by QITS integration:**
  - `X-QITS-Key`
  - `X-QITS-Timestamp`
  - `X-QITS-Nonce`
  - `X-QITS-Signature`
- **Content-Type:** `application/json`
- **Company service required:**
  - `id = 2`
  - `slug = formularios-whatsapp-api`
  - `nombre = Gestion de envio de formularios via WhatsApp API`

**Contracted Service Control:**
- Company services are stored in `empresa_servicio`.
- `Empresa` uses `servicios()` relationship for service binding.
- Service-check methods used in the module: `hasActiveService` and `hasActiveServiceBySlug`.
- If service is not active for the company:
  - `status = skipped_security`
  - `failure_reason = service_not_enabled`

**Implemented Components:**
- `routes/api.php`
- `routes/web.php`
- `app/Http/Requests/Api/WhatsAppHub/WordpressFormNotificationIngestRequest.php`
- `app/Http/Controllers/Api/WhatsAppHub/WordpressFormNotificationIngestController.php`
- `app/Services/WhatsAppHub/WordpressFormNotificationIngestService.php`
- `app/Jobs/WhatsAppHub/DispatchWordpressFormNotificationJob.php`
- `app/Services/WhatsAppHub/WhatsAppApiClient.php` (`sendTemplate`)
- `app/Models/WhatsappFormNotification.php`
- `app/Models/FormNotificationPublicLink.php`
- `app/Models/EmpresaWhatsAppSetting.php`
- `app/Http/Controllers/PublicFormNotificationController.php`
- `resources/views/public/form-notification-show.blade.php`
- `app/Http/Livewire/Admin/Empresas/EmpresasManager.php`
- `resources/views/livewire/admin/empresas/empresas-manager.blade.php`
- `config/whatsapp_hub.php`
- `config/integration_security.php`

**Tables / Models Involved:**
- `whatsapp_form_notifications`
- `form_notification_public_links`
- `empresa_whatsapp_settings`
- `empresa_servicio`

**Idempotency Behavior:**
- Unique key: `UNIQUE(empresa_id, source_system, source_record_id)`.
- Duplicate requests return idempotent success.
- Duplicates do not create a new public link.
- Duplicates do not dispatch a new job.
- Duplicates do not consume rate-limit quota.

**Supported Notification Statuses:**
- `pending`
- `queued`
- `awaiting_template`
- `skipped_no_recipient`
- `skipped_no_opt_in`
- `skipped_security`
- `sent`
- `delivered`
- `read`
- `failed`
- `expired`

**WhatsApp Settings per Company (Admin UI):**
- Managed from existing Companies flow, inside modal **WordPress UTM Integration**.
- Fields:
  - `destination_phone`
  - `destination_opt_in`
  - `destination_opt_in_at`
  - `destination_opt_in_source`
- Validations:
  - `destination_phone` required when `destination_opt_in = true`.
  - `destination_opt_in_at` required when `destination_opt_in = true`.
  - `destination_opt_in_source` required when `destination_opt_in = true`.
- Phone normalization at save:
  - stores digits only.
  - valid length: 10 to 15 digits.
  - cannot start with `0`.
  - example: `+57 (300) 123-45 67` -> `573001234567`.

**Template Messaging (No Free Text):**
- The module sends only WhatsApp template messages.
- Template config keys:
  - `whatsapp_hub.form_notifications_template.name`
  - `whatsapp_hub.form_notifications_template.language`
  - `whatsapp_hub.form_notifications_template.status`
- Expected status values: `approved | pending | disabled`.
- If template status is not `approved`, notification is stored as `awaiting_template`.

**Global WhatsApp Sender Credentials (QITS):**
- Environment variables:
  - `WHATSAPP_TOKEN`
  - `WHATSAPP_PHONE_ID`
  - `WHATSAPP_WABA_ID`
- Config mapping (`config/whatsapp_hub.php`):
  - `whatsapp_hub.template_sender.access_token`
  - `whatsapp_hub.template_sender.phone_number_id`
  - `whatsapp_hub.template_sender.waba_id`
- Current runtime usage:
  - `WHATSAPP_TOKEN` is used as global sender access token.
  - `WHATSAPP_PHONE_ID` is used as global sender `phone_number_id` (approved QITS number).
  - `WHATSAPP_WABA_ID` is available in config and reserved for future administration; it is not required by current template dispatch.

**Separation of Responsibilities (Sender vs Recipient):**
- Global QITS credentials define which approved sender number dispatches template messages.
- `empresa_whatsapp_settings.destination_phone` defines recipient phone number.
- `empresa_whatsapp_settings.destination_opt_in`, `destination_opt_in_at`, and `destination_opt_in_source` validate recipient authorization.
- `empresa_whatsapp_settings` is no longer treated as source of sender credentials in this flow.

**Components Affected by Sender-Credential Change:**
- `config/whatsapp_hub.php`
- `app/Services/WhatsAppHub/WhatsAppApiClient.php`

**Updated Template Dispatch Path (Sender Credentials):**
```
WordPress form submit
  -> QITS receives and validates payload
  -> QITS resolves empresa context
  -> QITS validates service active + destination_phone + opt-in + template approved + rate limit
  -> Job calls WhatsAppApiClient::sendTemplate
  -> sendTemplate uses WHATSAPP_TOKEN + WHATSAPP_PHONE_ID as global QITS sender credentials
  -> destination_phone is used only as recipient
  -> Meta/WhatsApp receives template message
```

**Operational Note (.env):**
For production, define:
```
WHATSAPP_TOKEN=
WHATSAPP_PHONE_ID=
WHATSAPP_WABA_ID=
```
`WHATSAPP_WABA_ID` may be configured even though current template dispatch does not consume it directly.

**Recommended Template Content (Operational Reference):**
```
Nueva solicitud de contacto recibida.

Nombre: {{1}}
Servicio: {{2}}
Telefono: {{3}}

Presiona el boton para consultar el detalle en QITS.

Boton: Ver solicitud
URL: https://app.qits.com.co/s/{{1}}
```

**Secure Public Link (/s/{token}):**
- Public route: `GET /s/{token}`.
- Plain token is used only in URL.
- `token_hash` is persisted for lookup/validation.
- `token_encrypted` is persisted so job can reconstruct button URL parameter.
- Plain token is never stored in database.
- Public view validation requires:
  - `is_active = true`
  - `revoked_at IS NULL`
  - `expires_at > now`
- Public view does not expose:
  - `raw_payload_json`
  - `token`
  - `token_hash`
  - `token_encrypted`
- Public view displays only safe fields:
  - `nombre`
  - `servicio`
  - `telefono`
  - `email`
  - `form_name`
  - `page_url`
  - `submitted_at`
  - `mensaje/comentario`

**Rate Limit / Anti-Abuse:**
- Config keys in `config/integration_security.php`:
  - `wordpress_form_notifications_rate_limit.max_per_minute`
  - `wordpress_form_notifications_rate_limit.max_per_hour`
  - `wordpress_form_notifications_rate_limit.max_per_day`
- Environment variables:
  - `INTEGRATION_WP_FORM_NOTIFICATIONS_MAX_PER_MINUTE`
  - `INTEGRATION_WP_FORM_NOTIFICATIONS_MAX_PER_HOUR`
  - `INTEGRATION_WP_FORM_NOTIFICATIONS_MAX_PER_DAY`
- Defaults:
  - 10/minute
  - 100/hour
  - 500/day
- Limit is applied before `dispatchSendJob`.
- On exceed:
  - `status = skipped_security`
  - `failure_reason = rate_limit_exceeded`
- Anti-abuse outcome is recorded in `provider_response_json` and `integration_security_logs`.
- Idempotent duplicates do not consume quota.

**Current Implemented Limitations:**
- No automatic PDF generation.
- No attachment sending.
- No free-text WhatsApp messages.
- Flow is only supported for forms delivered through AG UTM Tracker plugin.
- `delivered/read` confirmations depend on WhatsApp webhook processing when available.
- Template must be approved in Meta before setting `form_notifications_template.status = approved`.

---

## 6. Database Schema Highlights

### Core Tables
| Table | Purpose | Key Constraint |
|-------|---------|-----------------|
| `empresas` | Company/account entities | PK: id |
| `leads` | Lead records | FK: empresa_id |
| `lead_events` | Lead activity log | FK: lead_id |
| `lead_documents` | Document storage | FK: lead_id |
| `seo_utm_conversions` | UTM tracking | UNIQUE(empresa_id, source_system, source_record_id) |
| `outbound_messages` | WhatsApp messages | FK: empresa_id, lead_id |
| `empresa_whatsapp_settings` | WhatsApp config | FK: empresa_id |
| `empresa_seo_properties` | SEO properties | FK: empresa_id |
| `integration_security_logs` | Auth audit | FK: empresa_id |
| `content_imports` | Excel/grid import traceability for content module | FK: empresa_id, imported_by |
| `content_articles` | Imported article records and lifecycle state | FK: content_import_id |
| `content_article_steps` | Step state for objective/drafting/video_instagram | UNIQUE(content_article_id, step_type) |
| `content_master_templates` | Master prompt templates registry | UNIQUE(key) |
| `content_master_template_versions` | Versioned prompt templates | UNIQUE(content_master_template_id, version_number) |
| `content_article_generations` | Prompt generation snapshots per article/step | FK: content_article_id, template_version_id |
| `content_article_files` | Versioned final files per article | UNIQUE(content_article_id, version_number) |

### Migration Strategy
- Migrations use timestamp prefix: `YYYY_MM_DD_HHMMSS_description`
- Schema modifications are backward-compatible
- See `/database/migrations` for version history

---

### Content Management Module (Current State)

**Status:** Persistence layer, XLSX import workflow, main article listing, operational flows for steps `objective`, `drafting` and `video_instagram`, plus private final-file upload/versioning implemented.

**Included in this phase:**
- Database schema for imports, articles, step tracking, master templates, template versions, prompt generations and versioned files
- Eloquent models and base relationships
- Structural state constants in models
- Model-level guard that prevents:
  - more than one active version per master template
  - marking `objective` step as `ready` when `refined_objective` or `refined_target_audience` is empty
- Scope of these guards:
  - applied on Eloquent model writes (`create`, `save`)
  - not a hard guarantee for direct SQL, Query Builder bulk updates or external concurrency races
- XLSX import service with:
  - temporary local storage
  - full-file validation before persistence
  - duplicate detection by empresa + fecha + tema normalizado
  - transactional persistence without partial import
  - temporary XLSX deletion after processing
- Livewire 2 import screen with:
  - authorized empresa selection based on current `User::isAdmin()` / `empresa_id` visibility rules
  - mandatory tone selection (`tuteo` | `usteo`)
  - temporary XLSX staging
  - full-file preview/validation before persistence
  - error and duplicate visualization
  - manual confirmation step before creating records
- Web route and Blade wrapper:
  - `GET /admin/content-management`
  - view `admin.content-management.index`
  - Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleIndex`
  - `GET /admin/content-management/imports`
  - view `admin.content-management.imports`
  - Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentImportManager`
- Detail route and operational screen for step `objective`:
  - `GET /admin/content-management/articles/{article}`
  - server-side access check through tenant visibility
  - view `admin.content-management.show`
  - Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleObjectiveDetail`
- Objective prompt service:
  - `App\Services\ContentManagement\ContentObjectivePromptService`
  - resolves the active `objective` template version from `content_master_template_versions`
  - assembles the final prompt from the active template body plus article context
  - persists one `content_article_generations` row per generate/regenerate action
  - never overwrites prior prompt generations
- Drafting prompt service and UI:
  - `App\Services\ContentManagement\ContentDraftingPromptService`
  - `App\Http\Livewire\Admin\ContentManagement\ContentArticleDraftingPanel`
  - resolves the active `drafting` template version from `content_master_template_versions`
  - resolves `site_url` exclusively from `content_articles -> content_imports -> empresa -> seoProperty -> site_url`
  - does not use `wordpress_site_url`
  - does not use `ProyectoEmpresa.url`
  - persists one `content_article_generations` row per generate/regenerate action
  - never overwrites prior prompt generations
- Video Instagram prompt service and UI:
  - `App\Services\ContentManagement\ContentVideoInstagramPromptService`
  - `App\Http\Livewire\Admin\ContentManagement\ContentArticleVideoInstagramPanel`
  - resolves the active `video_instagram` template version from `content_master_template_versions`
  - assembles a copy-ready prompt that explicitly instructs the operator to attach the final article document in Word or PDF before using it in ChatGPT
  - does not inject or simulate final article content
  - persists one `content_article_generations` row per generate/regenerate action
  - never overwrites prior prompt generations
- Final file upload and download flow:
  - `App\Services\ContentManagement\ContentFinalFileService`
  - `App\Http\Livewire\Admin\ContentManagement\ContentArticleFinalFilePanel`
  - `App\Http\Controllers\Admin\ContentManagement\ContentArticleFileDownloadController`
  - accepts only DOCX and PDF with extension, MIME, non-empty-file and size validation
  - stores files through Laravel `Storage` on a private disk/path configured in `config/content_management.php`
  - preserves original filename only as metadata and generates a private physical filename per version
  - creates one new `content_article_files` row per upload and never overwrites previous versions
  - resolves the next `version_number` inside a transaction with `lockForUpdate()` on the article row
  - removes the stored file if persistence fails after storage
  - exposes downloads only through an authenticated, tenant-validated controller route
  - after the first valid final file, moves the article to `operational_stage = completed` and `main_status = unpublished`
- Delivery and publication flow:
  - `App\Services\ContentManagement\ContentArticleReleaseService`
  - `App\Http\Livewire\Admin\ContentManagement\ContentArticleDeliveryPublicationPanel`
  - delivery and publication remain fully independent manual events
  - delivery requires at least one final file and only updates `delivered_at` / `delivered_by`
  - delivery does not infer publication and can be corrected explicitly back to `null`
  - publication requires a valid `published_url`
  - publication updates `published_at`, `published_by`, `published_url`, `main_status = published` and keeps `operational_stage = completed`
  - publication does not alter delivery fields
  - published URL can be updated explicitly after publication without replaying delivery logic
- Temporary XLSX purge flow:
  - artisan command `content-management:prune-temp-imports`
  - scheduler entry in `app/Console/Kernel.php`
  - purge limited to `tmp/content-management/imports`
  - TTL configured in `config/content_management.php`

**Not included yet:**
- Automatic publication/integrations with external publishing targets

**Tenant resolution:**
- `content_imports` belongs directly to `empresas`
- `content_articles` belongs to `content_imports` only
- tenant for an article is resolved via `content_articles -> content_imports -> empresa`

**Key lifecycle enums:**
- `content_articles.main_status`: `pending | processing | unpublished | published`
- `content_articles.operational_stage`: `pending | strategic_refinement | drafting | video_instagram | final_file | completed`
- `content_article_steps.step_type`: `objective | drafting | video_instagram`
- `content_article_steps.step_status`: `pending | in_progress | ready`
- `content_articles.tone`: `tuteo | usteo`

**Visible labels:**
- User-facing Content Management labels are centralized in `App\Support\ContentManagementLabels`.
- Internal enum values remain unchanged in database, models, services and queries.
- Blade views must use the centralized labels for:
  - main statuses
  - operational stages
  - step types
  - step statuses
- UI should not print internal codes such as `processing`, `pending`, `drafting`, `objective`, `video_instagram`, `ready_by` or `ready_at` as visible text.

**Initial XLSX import contract (implemented):**
- accepted format: `.xlsx` only
- required headers:
  - `Fecha`
  - `Tema del artículo`
  - `Objetivo estratégico`
  - `Público objetivo`
- required external import option:
  - `tone` (`tuteo` | `usteo`)
- validation result includes:
  - total rows
  - valid rows
  - duplicate rows
  - row-level errors with row number, field and message
- if any row fails validation or duplicates an existing article, nothing is persisted
- definitive confirmation creates:
  - one `content_imports` record
  - `content_articles`
  - 3 `content_article_steps` per article
  - all inside one database transaction
- imported articles start with:
  - `content_articles.main_status = pending`
  - `content_articles.operational_stage = pending`
- data correction migration:
  - moves only legacy unstarted records from `processing` to `pending`
  - condition: `main_status = processing`, `operational_stage = pending`, and no `content_article_generations`
  - records with generations or started operational stages remain untouched
- import UI loading indicators:
  - button loading states use Livewire 2 `wire:loading.flex` with inline `display: none` at rest
  - validation and confirmation buttons target only `validateImport` and `confirmImport` respectively
  - text remains visible at rest and loading text/spinner is shown only during the matching action

**Main article listing (implemented):**
- visible fields:
  - empresa
  - fecha
  - tema
  - estado principal
  - etapa operativa
  - entregado
  - publicado
  - última actualización
  - acción
- search:
  - `content_articles.topic`
  - `empresas.nombre`
- combinable filters:
  - empresa
  - estado principal
  - periodo (`all | current_month | previous_month | next_month`)
- enforced priority ordering:
  - `processing`
  - `unpublished` in current month
  - other `unpublished`
  - `published`
- internal sort:
  - processing by `updated_at desc`
  - current-month unpublished by `article_date asc`
  - other unpublished by `article_date asc`
  - published by `published_at desc`
- action label:
  - `Generar` when `operational_stage = pending`
  - `Continuar` otherwise
- tenant isolation:
  - admin users can view all empresas currently visible under existing rules
  - non-admin users are restricted server-side to their `empresa_id`
  - detail route does not rely on UI filters; it revalidates access before rendering

**Objective operational flow (implemented):**
- UI composition:
  - rendered as card `Paso 1 · Definir objetivo y público`
  - groups step status, explanation, Prompt 1 generation, prompt copy action, selected prompt, refined fields, history and ready action in one visual unit
  - remains inside `ContentArticleObjectiveDetail`; child components for later steps are still mounted separately
- visible article data:
  - empresa
  - fecha
  - tema
  - objetivo estrategico general
  - publico objetivo general
  - objetivo refinado
  - publico objetivo refinado
  - estado principal
  - etapa operativa
  - estado del paso Objetivo y publico
- prompt generation:
  - uses the active template where `content_master_templates.key = objective`
  - each click on generate/regenerate creates a new `content_article_generations` row
  - stored generation fields include:
    - `content_article_id`
    - `content_master_template_version_id`
    - `step_type = objective`
    - `final_prompt_text`
    - `generated_by`
    - `generated_at`
  - if no active `objective` template version exists, the UI shows:
    - `La plantilla necesaria para este paso no está configurada. Contacta al administrador.`
  - missing active template details are logged with template existence, template active state and active version count
- first-generation state transition:
  - `content_articles.main_status` changes from `pending` to `processing`
  - `content_articles.operational_stage = strategic_refinement`
  - objective step moves to `in_progress`
- regeneration behavior:
  - keeps the full prior history
  - does not reset article or step state once at least one objective generation already exists
- manual refinement capture:
  - updates only `refined_objective`
  - updates only `refined_target_audience`
  - does not mutate `strategic_objective_general` or `target_audience_general`
- ready transition:
  - `objective` can be marked `ready` only when both refined fields are non-empty
  - when marked ready, writes:
    - `content_article_steps.step_status = ready`
    - `ready_by`
    - `ready_at`

**Drafting operational flow (implemented):**
- UI composition:
  - rendered as card `Paso 2 · Redactar artículo`
  - groups step status, blocking message, Prompt 2 generation, prompt copy action, selected prompt, history and ready action in one visual unit
  - blocked prerequisites are shown explicitly as `Bloqueado`
- access and readiness preconditions:
  - `objective` step must already be `ready`
  - `refined_objective` must be present
  - `refined_target_audience` must be present
  - `EmpresaSeoProperty.site_url` must exist and be non-empty
  - tenant access is revalidated server-side on generate, regenerate and ready actions
- prompt generation:
  - uses the active template where `content_master_templates.key = drafting`
  - injects only:
    - `site_url`
    - `topic`
    - `refined_objective`
    - `refined_target_audience`
    - article `tone`
  - explicitly does not use:
    - `wordpress_site_url`
    - `ProyectoEmpresa.url`
- persistence:
  - each click on generate/regenerate creates one new `content_article_generations` row with:
    - `content_article_id`
    - `content_master_template_version_id`
    - `step_type = drafting`
    - `final_prompt_text`
    - `generated_by`
    - `generated_at`
- first-generation transition:
  - `content_articles.main_status` remains `processing`
  - `content_articles.operational_stage = drafting`
  - drafting step moves to `in_progress`
- regeneration behavior:
  - preserves the full prior drafting history
  - does not reset article state
  - does not modify the objective step
- ready transition:
  - drafting can be marked `ready` only when at least one drafting generation already exists
  - when marked ready, writes:
    - `content_article_steps.step_status = ready`
    - `ready_by`
    - `ready_at`
  - advances:
    - `content_articles.operational_stage = video_instagram`
    - `content_articles.main_status` remains `processing`

**Video Instagram operational flow (implemented):**
- UI composition:
  - rendered as card `Paso 3 · Crear contenido para video e Instagram`
  - groups step status, blocking message, Word/PDF attachment instruction, Prompt 3 generation, prompt copy action, selected prompt, history and ready action in one visual unit
  - blocked prerequisites are shown explicitly as `Bloqueado`
- access and readiness preconditions:
  - `drafting` step must already be `ready`
  - at least one `drafting` generation must already exist
  - tenant access is revalidated server-side on generate, regenerate and ready actions
- prompt generation:
  - uses the active template where `content_master_templates.key = video_instagram`
  - includes an explicit operator instruction to attach the final article document in Word or PDF before executing the prompt in ChatGPT
  - includes only minimal article context:
    - `topic`
  - explicitly does not:
    - inject final article content
    - simulate that the article document was already read
- persistence:
  - each click on generate/regenerate creates one new `content_article_generations` row with:
    - `content_article_id`
    - `content_master_template_version_id`
    - `step_type = video_instagram`
    - `final_prompt_text`
    - `generated_by`
    - `generated_at`
- first-generation transition:
  - `content_articles.main_status` remains `processing`
  - `content_articles.operational_stage = video_instagram`
  - video_instagram step moves to `in_progress`
- regeneration behavior:
  - preserves the full prior video_instagram history
  - does not reset article state
  - does not modify objective or drafting steps
- ready transition:
  - video_instagram can be marked `ready` only when at least one video_instagram generation already exists
  - when marked ready, writes:
    - `content_article_steps.step_status = ready`
    - `ready_by`
    - `ready_at`
  - advances:
    - `content_articles.operational_stage = final_file`
    - `content_articles.main_status` remains `processing`

**Master prompt template registration (implemented):**
- bootstrap source files are stored under:
  - `database/seeders/data/content-management/`
- registration is executed by:
  - `Database\Seeders\ContentMasterTemplatesSeeder`
- current mapping:
  - `objective` → `1_GENERACION OBJETIVO ARTICULO.txt`
  - `drafting` → `2_REDACCION ARTICULO.txt`
  - `video_instagram` → `3_GUION VIDEOS E INSTAGRAM.txt`
- versioning strategy:
  - one master template row per key in `content_master_templates`
  - initial version stored as `version_number = 1`
  - initial version marked `is_active = true`
  - master template marked `is_active = true`
- idempotency behavior:
  - rerunning the seeder does not duplicate templates or versions
  - if version `1` already exists with the exact same body, it is reused
  - if version `1` exists with different content, the seeder fails explicitly instead of overwriting historical content
- operator editing:
  - no operator UI exists for editing master templates in the current implementation

---

## 7. REST API Design

### Base URL
```
POST /api/{version}/{module}/{resource}
```

### Authentication
**Method:** HMAC-SHA256 signature (module-specific header scheme)  
**Payload format:** JSON

**Important:** Do not assume `X-Signature` for every endpoint. Some integrations use a module-specific signature header set.

**Module-specific schemes (current state):**
- **SEO UTM ingestion:** can use the `X-Signature` scheme documented below when implemented that way.
- **WordPress Form Notifications:** uses the following headers:
  - `X-QITS-Key`
  - `X-QITS-Timestamp`
  - `X-QITS-Nonce`
  - `X-QITS-Signature`
  - Signed path: `/api/wordpress/form-notifications`
  - Signature input: exact JSON body bytes sent in the request (no reformatting/normalization).

**Example (WordPress UTM Tracker):**
```bash
curl -X POST https://app.qits.com.co/api/seo/utm-conversions \
  -H "Content-Type: application/json" \
  -H "X-Signature: sha256=abc123..." \
  -d '{
    "source_record_id": "12345",
    "event_name": "click",
    "source": "google",
    "medium": "cpc",
    "campaign": "summer_2026",
    "conversion_datetime": "2026-03-28 10:00:00",
    "raw_payload_json": {
      "utm_source": "google",
      "utm_medium": "cpc",
      "utm_campaign": "summer_2026"
    }
  }'
```

### Response Codes
| Code | Scenario |
|------|----------|
| 200 | Success (idempotent duplicate) |
| 201 | Created (new record) |
| 400 | Bad request (malformed JSON) |
| 401 | Unauthorized (missing signature) |
| 403 | Forbidden (signature mismatch) |
| 404 | Not found |
| 422 | Validation error (with field-level details) |
| 500 | Server error |

---

## 8. Frontend Architecture (Livewire + Blade)

### Main Navigation
**Location:** `resources/views/navigation-menu.blade.php`

**Pattern:**
- Rendered from `resources/views/layouts/app.blade.php` through `@livewire('navigation-menu')`
- Uses Jetstream navigation components:
  - `x-jet-nav-link` for desktop
  - `x-jet-responsive-nav-link` for mobile/responsive navigation
- Current admin navigation entries:
  - Dashboard -> `route('admin.empresas')`
  - SEO -> `route('admin.seo')`
  - Gestión de Contenidos -> `route('admin.content-management.index')`
- Content Management active state uses:
  - `request()->routeIs('admin.content-management*')`

### Component Structure
**Location:** `app/Http/Livewire/`

**Pattern:**
```
Blade Template
    ↓ wire:model, wire:click
Livewire Component (PHP class)
    ↓ Public properties, computed
    ↓ #[On('event')] listeners
    ↓ Public methods
    ↓ Calls Service layer
    ↓ Updates view reactively
```

### Responsive Design Rules
- **Desktop:** HTML table with full features
- **Mobile:** Card-based layout with horizontal scroll fallback
- **Tailwind:** Utility-first approach (no custom components for simple layouts)

### Known Issues / Conventions
- Wire actions must exist on Livewire component (verified before closing PR)
- Modals: Use Jetstream's built-in modal structure
- Dropdowns: Avoid within overflow-x-auto containers (gets clipped)
- Grids: Explicit `col-span` required when using grid columns

---

## 9. Security Architecture

### Authentication Flow
```
User → Fortify (2FA, email verification) → Jetstream → Authenticated
```

### Authorization
- **Web:** session-authenticated access with role checks based on `TipoUsuario`, `User::isAdmin()` and explicit guards/`abort(403)` in components/routes
- **API:** Gate-based checks in middleware
- **Integration:** HMAC signature verification + scope validation

### Data Isolation
- **Row-Level Security:** `empresa_id` foreign key ensures data segregation
- **API Scope:** Each integration has specific allowed scopes (e.g., `seo.utm_conversions_ingest`)

### Audit & Logging
- **Integration Logs:** `integration_security_logs` table tracks all API calls
- **Exception Logs:** `storage/logs/laravel.log`
- **Request ID:** Injected into all responses for traceability

---

## 10. Monitoring & Observability

### Error Tracking
- **FormRequest failures:** Enhanced 422 responses with `error_code`, `request_id`, `failed_fields`, `debug.received_fields`
- **Service layer:** QueryException catch for duplicate detection with detailed logging
- **Controller:** Distinct HTTP status codes (201 vs 200) for created vs idempotent

### Logging
- **Location:** `storage/logs/laravel.log`
- **Rotation:** Daily (configurable in `config/logging.php`)
- **Channels:** `single`, `daily`, `slack` (via config)

### Request Tracing
- **X-Request-Id** header: Generated in FormRequest if not provided
- **Injected into response:** Allows client-side correlation
- **Used in error responses:** For support team debugging

---

## 11. Development Workflow

### Local Development (Laragon)
```bash
# Setup
composer install
npm install
npm run dev

# Database
php artisan migrate
php artisan db:seed

# Run
php artisan serve
```

### Testing
```bash
# Unit tests
php ./vendor/bin/phpunit tests/Unit

# Feature tests
php ./vendor/bin/phpunit tests/Feature

# Specific test
php ./vendor/bin/phpunit tests/Feature/Seo/UtmConversionIngestTest.php
```

**Current test environment note:**
- Content Management feature tests run under PHP 8.1 against MySQL with an isolated per-process schema strategy:
  - `.env.testing`
  - base database name: `qits_app_testing`
  - effective runtime database: `qits_app_testing_{pid|TEST_TOKEN}`
- `tests/CreatesApplication.php` provisions the effective schema before Laravel boots:
  - validates `APP_ENV=testing`
  - derives a unique schema name from the testing base name plus process token
  - drops and recreates only that isolated testing schema
- This prevents `RefreshDatabase` collisions and removes dependency on prior state left in a shared MySQL testing schema.
- This project does not currently have `pdo_sqlite` available in the PHP 8.1 CLI runtime, so `RefreshDatabase` for this module remains MySQL-based instead of SQLite.
- Test logging is directed to `stderr` in `.env.testing` to avoid dependency on writable shared log files during PHPUnit runs.

### Production Deployment (Shared Hosting)
- **PHP Version:** 8.1 required
- **Extensions:** PDO, JSON, OpenSSL, cURL
- **Build:** Pre-compiled assets (no runtime npm/composer required)
- **Migrations:** Applied manually via SSH

---

## 12. Configuration Management

### Environment Variables
**Critical files:**
- `.env` — Local development settings
- `.env.production` — Production credentials (not in version control)

**Key variables:**
```
APP_NAME=QITS
APP_ENV=local|production
APP_DEBUG=false (production)
DATABASE_URL=mysql://...
REDIS_URL=... (optional)
GOOGLE_OAUTH_CLIENT_ID=...
GOOGLE_OAUTH_CLIENT_SECRET=...
```

### Multi-environment Setup
- **Development:** `.env` with debug enabled
- **Staging:** `.env` with partial debug, test database
- **Production:** `.env.production` with locked versions, no debug

---

## 13. Common Workflows

### Adding a New API Endpoint
1. Define **route** in `routes/api.php` with middleware
2. Create **FormRequest** in `app/Http/Requests/Api/{Module}/`
3. Implement **Service** in `app/Services/{Module}/`
4. Create **Controller** in `app/Http/Controllers/Api/{Module}/`
5. Add **tests** in `tests/Feature/Api/{Module}/`
6. Document in `DESIGN_DOCS/` if complex

### Adding a New Integration
1. Create **config file** in `config/{integration_name}.php`
2. Create **Service** class in `app/Services/{IntegrationName}/`
3. Add **middleware** or **auth layer** for security
4. Create **tests** for payload validation
5. Document **API contract** and **idempotency strategy**

### Database Migration
1. Create migration: `php artisan make:migration description`
2. Edit `database/migrations/{timestamp}_description.php`
3. Test locally: `php artisan migrate --step`
4. Rollback test: `php artisan migrate:rollback --step=1`
5. Commit and deploy

---

## 14. Performance Considerations

### Query Optimization
- **Eager loading:** Use `with()` to prevent N+1 queries
- **Indexing:** Foreign keys automatically indexed; add composite indexes for frequent filters
- **Pagination:** Use `paginate()` for large result sets

### Caching Strategy
- **Route caching:** `php artisan route:cache` (production)
- **Config caching:** `php artisan config:cache`
- **Query result caching:** Redis (optional, configured in `config/cache.php`)

### Background Jobs
- **Configuration:** `config/queue.php`
- **Usage:** `dispatch(new SyncJob($data))` for async processing
- **Monitoring:** Use Laravel Horizon (if installed) or check `storage/logs/`

---

## Change Log (2026-07-14)

### AI Flows Module - Phase 1
- Added migration `2026_07_14_120000_create_ai_flows_module_tables.php`.
- Added Eloquent models:
  - `AiFlow`
  - `AiFlowVersion`
  - `AiFlowStep`
  - `AiFlowStepDependency`
  - `AiFlowVariable`
  - `AiFlowExecution`
  - `AiFlowExecutionStep`
  - `AiFlowExecutionValue`
  - `AiFlowStepGeneration`
  - `AiFlowStepResult`
  - `AiFlowStrategicOutput`
- Added base relationships from `Empresa` and `User` into the new module.
- Added `App\Services\AiFlows\AiFlowAccessService` with admin-only MVP access.
- Added `App\Support\AiFlowLabels` for Spanish labels.
- Implemented structural model rules for:
  - variable names in snake_case without spaces or accents
  - variable name uniqueness per flow version through DB constraint
  - dependency steps belonging to the same flow version
- Added automated tests for:
  - core relationships
  - long prompt/result persistence
  - strategic output ownership
  - variable uniqueness and name validation
  - dependency version validation
  - admin-only access service behavior
- No UI, parser, dynamic forms, prompt rendering, seeders or Content Management migration were implemented in this phase.

## Change Log (2026-07-08)

### Content Management Module - Phase 2 (XLSX import UI)
- Added route `admin.content-management.imports`
- Added wrapper view `resources/views/admin/content-management/imports.blade.php`
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentImportManager`
- Added validation summary partial for preview/import feedback
- Extended `ContentXlsxImportService` with preview/staged-file flow reused by the UI
- Kept validation and persistence in the service layer to avoid duplicating business rules in Livewire
- Added automated tests for:
  - component mount authorization and empresa visibility
  - service rollback on persistence failure

### Content Management Module - Phase 2.1 (route fix and temp XLSX purge)
- Fixed `GET /admin/content-management/imports` rendering path:
  - root cause was a stale Livewire auto-discovery manifest in `bootstrap/cache/livewire-components.php`
  - the route existed, but the Blade wrapper depended on a component alias that was missing from the cached manifest
  - the wrapper now mounts the component by class reference to avoid alias-manifest drift
- Added minimal orphaned temp XLSX purge support:
  - command `content-management:prune-temp-imports`
  - scheduled execution through Laravel scheduler
  - configurable TTL/disk in `config/content_management.php`
  - scope restricted to `tmp/content-management/imports`
- Added tests for:
  - authorized route access
  - unauthorized route blocking
  - pruning old temp XLSX files
  - preserving recent temp XLSX files

### Content Management Module - Phase 3 (main article listing)
- Added main route `admin.content-management.index`
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleIndex`
- Added main view `resources/views/admin/content-management/index.blade.php`
- Added listing view `resources/views/livewire/admin/content-management/content-article-index.blade.php`
- Added minimal detail route and view for next phase handoff:
  - `admin.content-management.articles.show`
  - `resources/views/admin/content-management/show.blade.php`
- Added `ContentAccessService` for tenant-scoped empresa/article visibility
- Implemented:
  - search by topic and empresa name
  - combined filters by empresa, main status and relative month period
  - priority ordering across processing, unpublished and published states
  - pagination with eager loading and no N+1 on empresa resolution
  - visual/textual state differentiation
  - Generate/Continue action entry point
- Added automated tests for:
  - tenant isolation in index
  - topic search
  - empresa search
  - combined filters
  - priority ordering
  - pagination
  - authorized access
  - forbidden access to another empresa article

### Content Management Module - Phase 3.1 (master template bootstrap)
- Added source prompt files to `database/seeders/data/content-management/`
- Added seeder `Database\Seeders\ContentMasterTemplatesSeeder`
- Added `DatabaseSeeder` registration for the master template bootstrap
- Implemented idempotent creation of:
  - `objective`
  - `drafting`
  - `video_instagram`
- Implemented explicit protection against silent overwrite when version `1` already exists with different content
- Added automated tests for:
  - exact 3-key registration
  - active version presence
  - body fidelity against approved source files
  - idempotent re-execution
  - failure on conflicting historical version `1`

### Content Management Module - Phase 4 (objective operational flow)
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleObjectiveDetail`
- Added service `App\Services\ContentManagement\ContentObjectivePromptService`
- Updated detail wrapper view `resources/views/admin/content-management/show.blade.php`
- Added detail Livewire view `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
- Implemented:
  - Prompt 1 generation from the active `objective` template version
  - independent generation history rows on every generate/regenerate action
  - first-generation transition to `strategic_refinement` / `in_progress`
  - manual capture of `refined_objective` and `refined_target_audience`
  - objective ready transition with `ready_by` and `ready_at`
  - server-side tenant revalidation on every sensitive action through `ContentAccessService`
- Added automated tests for:
  - active template version usage
  - independent generation history
  - first-generation state transition
  - no state reset on regeneration
  - forbidden generation after article tampering across empresas
  - refined field persistence without mutating general fields
  - blocking ready without both refined fields
  - allowing ready with audit fields recorded

### Content Management Module - Phase 5 (drafting operational flow)
- Added service `App\Services\ContentManagement\ContentDraftingPromptService`
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleDraftingPanel`
- Added detail subview `resources/views/livewire/admin/content-management/content-article-drafting-panel.blade.php`
- Extended the operational detail view to mount the drafting panel as a separate unit
- Implemented:
  - Prompt 2 generation from the active `drafting` template version
  - exclusive site URL resolution from `EmpresaSeoProperty.site_url`
  - explicit blocking when `objective` is not ready, refined fields are missing or `site_url` is absent
  - independent drafting generation history rows on every generate/regenerate action
  - first drafting generation transition to `operational_stage = drafting` and step `in_progress`
  - manual ready transition for drafting with `ready_by` and `ready_at`
  - move to `operational_stage = video_instagram` after drafting ready
- Added automated tests for:
  - objective-ready prerequisite
  - refined-fields prerequisite
  - required `EmpresaSeoProperty.site_url`
  - no fallback to `wordpress_site_url`
  - no fallback to `ProyectoEmpresa.url`
  - active drafting template version usage
  - prompt inclusion of URL, topic, refined fields and tone
  - independent drafting history
  - no state reset on regeneration
  - tenant blocking on tampered access
  - ready blocking without drafting generation
  - ready transition with audit fields and next operational stage

### Content Management Module - Phase 6 (video_instagram operational flow)
- Added service `App\Services\ContentManagement\ContentVideoInstagramPromptService`
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleVideoInstagramPanel`
- Added detail subview `resources/views/livewire/admin/content-management/content-article-video-instagram-panel.blade.php`
- Extended the operational detail view to mount the video_instagram panel as a separate unit
- Implemented:
  - Prompt 3 generation from the active `video_instagram` template version
  - explicit instruction to attach the final article document in Word or PDF before using the prompt
  - blocking when `drafting` is not ready or no drafting generation exists
  - independent video_instagram generation history rows on every generate/regenerate action
  - first video_instagram generation transition to `operational_stage = video_instagram` and step `in_progress`
  - manual ready transition for video_instagram with `ready_by` and `ready_at`
  - move to `operational_stage = final_file` after video_instagram ready
- Added automated tests for:
  - drafting-ready prerequisite
  - required drafting generation prerequisite
  - active video_instagram template version usage
  - explicit Word/PDF attachment instruction
  - no invented final article content
  - independent video_instagram history
  - no state reset on regeneration
  - tenant blocking on tampered access
  - ready blocking without video_instagram generation
  - ready transition with audit fields and final_file stage

### Content Management Module - Phase 7 (final file upload and versioning)
- Added service `App\Services\ContentManagement\ContentFinalFileService`
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleFinalFilePanel`
- Added download controller `App\Http\Controllers\Admin\ContentManagement\ContentArticleFileDownloadController`
- Added final file panel view `resources/views/livewire/admin/content-management/content-article-final-file-panel.blade.php`
- Extended the operational detail view to mount the final file panel as a separate unit
- Added private download route:
  - `admin.content-management.articles.files.download`
- Extended `config/content_management.php` with final file storage configuration:
  - `final_files.disk`
  - `final_files.base_dir`
  - `final_files.max_file_kb`
- Implemented:
  - manual upload of DOCX/PDF only
  - private Laravel Storage path per article
  - sequential versioning with full history in `content_article_files`
  - original filename preservation as metadata only
  - tenant-validated secure download without exposing physical paths
  - cleanup of stored files when database persistence fails
  - article transition to `completed` + `unpublished` after a valid final file upload
- Added automated tests for:
  - blocking upload before `video_instagram` is ready
  - accepting valid DOCX and PDF uploads
  - rejecting unsupported extension and inconsistent MIME
  - creating version 1 and version 2 without overwrite
  - preserving history without exposing `file_path`
  - tenant blocking for upload and download
  - authorized download response
  - stored-file cleanup on forced persistence failure
  - final stage/status transition after upload

### Content Management Module - Phase 8 (manual delivery and manual publication)
- Added service `App\Services\ContentManagement\ContentArticleReleaseService`
- Added Livewire component `App\Http\Livewire\Admin\ContentManagement\ContentArticleDeliveryPublicationPanel`
- Added delivery/publication panel view `resources/views/livewire/admin/content-management/content-article-delivery-publication-panel.blade.php`
- Extended the operational detail view to mount the delivery/publication panel as a separate unit
- Implemented:
  - manual delivery registration with `delivered_at` and `delivered_by`
  - explicit delivery correction back to `null`
  - requirement of at least one final file before delivery
  - manual publication with required `published_url`
  - explicit published URL update flow
  - separation between delivery and publication state transitions
  - article transition to `main_status = published` while keeping `operational_stage = completed` on publication
- Added automated tests for:
  - blocking delivery without final file
  - delivery audit fields without implicit publication
  - explicit delivery correction
  - valid URL requirement for publication
  - independent publication audit fields and status update
  - published-without-delivered and delivered-without-published scenarios
  - explicit published URL update
  - tenant blocking on delivery and publication
  - listing reflection of delivery/publication independence

### Content Management Module - Phase 1
- Added migration `2026_07_08_120000_create_content_management_module_tables.php`
- Added Eloquent models:
  - `ContentImport`
  - `ContentArticle`
  - `ContentArticleStep`
  - `ContentMasterTemplate`
  - `ContentMasterTemplateVersion`
  - `ContentArticleGeneration`
  - `ContentArticleFile`
- Added base relationships from `Empresa` and `User` into the new module
- Documented approved persistence rules:
  - imports belong to company and importing user
  - articles always belong to an import
  - no `empresa_id` in `content_articles`
  - three fixed prompt steps
  - versioned master templates and versioned final files
- Added model-level business-rule enforcement for:
  - single active template version per master template
  - `objective` step cannot be marked `ready` without refined fields

### Content Management Module - Phase 2 (initial XLSX support)
- Added dependency `phpoffice/phpspreadsheet` for `.xlsx` parsing
- Added service `App\Services\ContentManagement\ContentXlsxImportService`
- Implemented temporary-file flow on local storage with deletion in `finally`
- Implemented full XLSX validation before persistence
- Implemented duplicate detection by company + article date + normalized topic
- Implemented transactional persistence:
  - creates one `content_imports` row
  - creates `content_articles`
  - initializes the three `content_article_steps` per article
- Added automated tests for:
  - valid XLSX
  - missing header
  - invalid date
  - empty required field
  - no partial persistence when a row is invalid
  - duplicate detection

## Change Log (2026-03-28)

### SEO UTM Ingestion Refactor
- **Implemented idempotent ingestion with `source_record_id` as canonical key**
  - Added database constraint: `UNIQUE(empresa_id, source_system, source_record_id)`
  - Modified `UtmConversionIngestService` to catch `QueryException` (MySQL error 1062) for duplicate handling
  - Returns tuple `[conversion, created]` to distinguish new records (201) from idempotent duplicates (200)

- **Enhanced FormRequest validation observability**
  - Modified `UtmConversionIngestRequest::failedValidation()` to return structured 422 response
  - Added fields: `error_code`, `request_id`, `failed_fields`, `debug.received_fields`
  - Improves client-side debugging of validation failures

- **Improved API response distinction**
  - Controller now returns **201 Created** for new conversions
  - Controller now returns **200 OK** for idempotent duplicate submissions
  - Both include `created` flag in response body for client clarity

- **Service layer enhancements**
  - `UtmConversionIngestService::isDuplicateKeyException()` helper method
  - `UtmConversionIngestService::normalize()` validates `source_record_id` as required string
  - `source_system` set server-side to `'wordpress_utm_tracker'` (immutable)

- **Payload validation & compatibility**
  - WordPress plugin must send: `source_record_id` as string (not numeric)
  - WordPress plugin must use field names: `source`, `medium`, `campaign`, `term`, `content` (not `utm_*` variants)
  - WordPress plugin must use `event_name` field (not `event_type`)
  - Optional: Wrap original payload in `raw_payload_json` for audit trail

### Files Modified
- `app/Http/Requests/Api/Seo/UtmConversionIngestRequest.php` — Added source_record_id validation, enhanced failedValidation()
- `app/Services/Seo/UtmConversionIngestService.php` — Implemented idempotency with QueryException catch
- `app/Http/Controllers/Api/Seo/UtmConversionIngestController.php` — Distinct 201/200 responses, unpacks result tuple

---

## Appendix: Useful Commands

```bash
# Create migration
php artisan make:migration create_table_name

# Create model + migration + factory
php artisan make:model ModelName -mf

# Create controller + model
php artisan make:controller Api/Module/ResourceController -m ModelName

# Create FormRequest
php artisan make:request Api/Module/ResourceRequest

# Cache routes & config
php artisan route:cache && php artisan config:cache

# Clear cache
php artisan cache:clear && php artisan config:clear

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback --step=1

# Queue jobs locally
php artisan queue:work
```

---

**Document Version:** 1.0  
**Maintained by:** Development Team  
**Last Review:** 2026-07-09
