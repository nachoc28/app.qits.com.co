# LOG

## 2026-07-14 (ai flows phase 5A manual loading validation)

### Context
- Scope limited to validating that administrators can load and execute a real AI Flow manually from the existing interface.
- No market research seeder, 15-step prompt loading, Content Management migration, OpenAI integration, file upload or document generation was implemented.

### Validation performed
- Added `tests/Feature/AiFlows/AiFlowManualLoadingWorkflowTest.php`.
- The test covers a 3-step manual flow:
  - `Validador de brief`
  - `Investigador de fuentes`
  - `Analisis de mercado`
- The validation exercises:
  - manual flow creation
  - draft version creation
  - manual stage creation with long prompt text
  - variable detection and invalid-token preview availability
  - variable synchronization
  - output variable configuration with `source_step_id`
  - explicit stage dependencies
  - version publication
  - execution creation for an `Empresa`
  - variable value entry
  - prompt generation
  - result saving
  - stage completion
  - downstream unlocking
  - output variable replacement from prior stage results

### Findings
- The existing interface supports the manual loading and execution path for the reduced 3-step validation flow.
- No functional blocker was found that required UI, service or persistence changes.
- The test avoids brittle text assertions around accented strings and validates key transitions through database state and generated prompt content.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows\AiFlowManualLoadingWorkflowTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (1 test, 32 assertions)` for the focused manual-loading workflow
  - `OK (110 tests, 323 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 4 strategic outputs)

### Context
- Scope limited to marking saved GPT results as strategic outputs for an `Empresa` and consulting them from the admin area.
- No market research seeder, Content Management migration, OpenAI integration, uploads or automatic document generation were implemented.

### Changes made
- Added `App\Services\AiFlows\AiFlowStrategicOutputService`.
- Extended `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow` with strategic-output marking state and action.
- Extended `resources/views/livewire/admin/ai-flows/ai-flow-execution-show.blade.php` with the `Marcar como resultado estrategico` panel beside the latest saved result.
- Added `App\Http\Livewire\Admin\AiFlows\AiFlowStrategicOutputIndex`.
- Added strategic-output admin views under `resources/views/admin/ai-flows/strategic-outputs/`.
- Added `resources/views/livewire/admin/ai-flows/ai-flow-strategic-output-index.blade.php`.
- Added routes:
  - `admin.ai-flow-strategic-outputs.index`
  - `admin.ai-flow-strategic-outputs.show`
- Updated AI Flows navigation active state and internal admin links.
- Added `tests/Feature/AiFlows/AiFlowStrategicOutputTest.php`.
- Updated `ARCHITECTURE.md` with Phase 4 implementation details.

### Rules implemented
- Only results from completed execution stages can be marked.
- Empty result content is rejected.
- Supported types are:
  - `strategic_report`
  - `executive_summary`
  - `current_strategic_base`
- Marking creates a historical `ai_flow_strategic_outputs` row with copied content, title, source links, user and date.
- The new output becomes current for its `empresa_id + type`.
- Previous current outputs for the same `empresa_id + type` are set to `is_current = false`.
- Outputs from other companies or other types are not affected.
- Current-output control is transactional in service code and does not use a MySQL partial unique index.
- Access remains Administrador-only through `AiFlowAccessService`.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (109 tests, 291 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 3C GPT results and step completion)

### Context
- Scope limited to copying the latest generated prompt, saving external GPT results, maintaining result history and completing execution stages.
- No current strategic outputs, market research seeders, Content Management migration, OpenAI integration or automatic file generation were implemented.

### Changes made
- Added `App\Services\AiFlows\AiFlowStepResultService`.
- Added `App\Services\AiFlows\AiFlowStepCompletionService`.
- Extended `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow` with:
  - copy feedback for latest generated prompt
  - result textarea state
  - result saving action
  - stage completion action
  - latest result and historical result data
- Rebuilt `resources/views/livewire/admin/ai-flows/ai-flow-execution-show.blade.php` to include:
  - `Copiar prompt`
  - external GPT result textarea
  - `Guardar resultado`
  - `Marcar etapa como completada`
  - latest saved result
  - collapsible result history
- Added `tests/Feature/AiFlows/AiFlowStepResultCompletionTest.php`.
- Updated `ARCHITECTURE.md` with the Phase 3C implementation state.

### Rules implemented
- Copying a prompt uses Alpine and `navigator.clipboard` when available.
- Copy action shows local feedback and does not modify the database.
- GPT result saving requires non-empty text.
- Result saving requires an existing prompt generation.
- Results are associated to the latest generation of the stage.
- Multiple result rows are allowed and shown historically.
- Completing a stage requires at least one saved result.
- Completing a stage sets `status = completed`, `completed_by` and `completed_at`.
- When all stages are completed, the parent execution becomes `completed`.
- Blocked stages reject result saving and completion server-side.
- Completing a stage recalculates downstream availability because `blocked` remains derived, not persisted.
- Output variables resolve from the latest saved result of the configured source step inside the same execution.
- Output variables without available source result still fail prompt generation with a clear message.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (96 tests, 258 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 3B dynamic variables and prompt rendering)

### Context
- Scope limited to dynamic variable forms in execution detail, saving execution values, rendering final prompts and storing prompt generation history.
- No advanced copy tracking, GPT result saving, stage completion, strategic outputs, market research seeders or Content Management migration were implemented.

### Changes made
- Added `App\Services\AiFlows\AiFlowPromptRenderService`.
- Extended `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow` with:
  - dynamic variable value hydration
  - variable saving per execution step
  - prompt generation action
  - local step messages/errors
  - latest generation/history data
- Updated `resources/views/livewire/admin/ai-flows/ai-flow-execution-show.blade.php` with:
  - dynamic input/textarea rendering
  - output variable notices
  - `Guardar variables`
  - `Generar prompt` / `Regenerar prompt`
  - latest prompt readonly textarea
  - collapsible generation history
- Added `tests/Feature/AiFlows/AiFlowPromptExecutionTest.php`.
- Updated `ARCHITECTURE.md` with the Phase 3B implementation state.

### Rules implemented
- Prompt rendering uses the execution step's frozen `AiFlowStep.base_prompt`.
- Placeholders are replaced exactly using configured variables.
- Textarea line breaks are preserved.
- Missing required variables block generation.
- Unconfigured prompt variables block generation.
- Output variables are prepared through latest source-step result lookup and block generation when no result exists.
- Prompt generation creates a new `ai_flow_step_generations` row every time.
- `variables_snapshot_json` stores variable name, label, scope, source and value used.
- Generating a prompt moves a step from `pending` to `in_progress`.
- Saved variable values persist in `ai_flow_execution_values` with `filled_by` and `filled_at`.
- Saving variables updates the logical existing value for the same execution/variable/scope instead of creating duplicates.
- Blocked stages do not show editable variable forms and reject prompt actions server-side.
- Only Administrador users can access execution prompt actions.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (84 tests, 232 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 3A base executions)

### Context
- Scope limited to creating executions of published AI flows for an `Empresa` and displaying read-only base progress by stage.
- No dynamic variable execution forms, final prompt rendering, prompt copy action, GPT result saving, strategic outputs, market research seeders or Content Management migration were implemented.

### Changes made
- Added `App\Services\AiFlows\AiFlowExecutionService`.
- Added Livewire 2 components:
  - `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionIndex`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionForm`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow`
- Added routes:
  - `admin.ai-flow-executions.index`
  - `admin.ai-flow-executions.create`
  - `admin.ai-flow-executions.show`
- Added Blade wrapper views under `resources/views/admin/ai-flows/executions/`.
- Added Livewire views for execution listing, creation and detail.
- Updated AI Flows navigation access so the module exposes `Flujos` and `Ejecuciones`.
- Added `tests/Feature/AiFlows/AiFlowExecutionBaseTest.php`.
- Updated `ARCHITECTURE.md` with the Phase 3A implementation state.

### Rules implemented
- Only Administrador users can list, create and view executions.
- Execution access and empresa access are checked through `AiFlowAccessService`.
- Only active flows with a published version are offered in the creation UI.
- Attempts to start a flow without a published version are rejected server-side.
- Execution creation runs inside a DB transaction.
- Created executions store:
  - `empresa_id`
  - `ai_flow_id`
  - frozen `ai_flow_version_id`
  - `title`
  - `status = in_progress`
  - `started_by`
  - `started_at`
- Active steps from the published version create `ai_flow_execution_steps` with `status = pending`.
- Inactive version steps are not initialized.
- `blocked` is visual only and is not stored in the database.
- Blocked calculation:
  - explicit dependencies require completed dependency execution steps
  - steps without explicit dependencies use sequential dependency by position
  - the first step without explicit dependencies is available as `Pendiente`

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (73 tests, 199 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 2D variable configuration)

### Context
- Scope limited to configuring variables detected in active prompts of an AI flow version.
- No client execution flow, dynamic execution forms, final prompt rendering, prompt copying, GPT result saving, market research seeders or Content Management migration were implemented.

### Changes made
- Extended `App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow` with:
  - variable synchronization from active step prompts
  - variable edit form
  - unused-variable detection
  - invalid-token display for active prompts
  - draft-only synchronization/editing
- Updated `resources/views/livewire/admin/ai-flows/ai-flow-version-show.blade.php` with the `Variables del flujo` section.
- Adjusted `App\Models\AiFlowVariable` so `output` variables require `source_step_id` but do not require `ai_flow_step_id`.
- Added `tests/Feature/AiFlows/AiFlowVariableConfigurationTest.php`.
- Updated `ARCHITECTURE.md` with the Phase 2D implementation state.

### Rules implemented
- Variables are synchronized only from valid placeholders in active prompts.
- Synchronization creates missing variables and does not duplicate existing variables.
- Synchronization does not delete configured variables that disappeared from prompts; those are shown as `No usada`.
- New variable defaults:
  - label generated from `name`
  - `scope = global`
  - `is_required = true`
  - `position` by first appearance
  - `input_type = textarea` for long-text hints, otherwise `input`
- Variable `name` is not editable from the UI.
- Draft versions can edit label, input type, scope, required flag, help text, placeholder, default value, position and scope-specific step references.
- Published or archived versions are read-only for variable configuration.
- `step` scope requires a step from the same version.
- `output` scope requires a source step from the same version.
- `global` scope clears step/source references.
- Publication still uses `AiFlowVersionService`; missing configured variables block publication, synchronized/configured variables allow publication when the version is otherwise valid.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (62 tests, 167 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 2C basic step builder)

### Context
- Scope limited to the basic step builder inside an AI flow version detail screen.
- No variable configuration, dynamic forms, client execution flow, final prompt rendering for executions, GPT result saving, market research seeders or Content Management migration were implemented.

### Changes made
- Extended `App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow` with draft-only step creation, editing and active/inactive toggling.
- Updated `resources/views/livewire/admin/ai-flows/ai-flow-version-show.blade.php` to include:
  - step form
  - broad `base_prompt` textarea
  - detected variable preview
  - invalid token preview
  - ordered step list
  - one optional dependency selector
- Added `tests/Feature/AiFlows/AiFlowStepBuilderTest.php`.
- Updated `ARCHITECTURE.md` with the Phase 2C implementation state.

### Rules implemented
- Only Administrador users can access the step builder through the existing admin-only routes/components.
- Steps can be created or edited only while the version is `draft`.
- Published or archived versions show a clear restriction and do not allow step edits.
- `step_key` is unique per version and must be lowercase without spaces or accents.
- `position` is required and unique per version.
- Steps are not physically deleted in this phase; draft versions can toggle `is_active`.
- Prompt preview uses `AiFlowVariableParser` and does not create variables automatically.
- One explicit dependency can be selected from previous steps of the same version.
- Dependencies pointing to another version, the same step or a later step are rejected.
- Version publication still fails when active prompts contain detected variables that are not configured.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (52 tests, 139 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 2B admin UI)

### Context
- Scope limited to the basic administrative UI for listing, creating and editing AI flows, managing versions and publishing versions through existing services.
- No complete step builder, prompt base editing, visual variable detection, variable configuration, client execution, dynamic forms, final prompt generation, market research seeders or Content Management migration were implemented.

### Changes made
- Added admin routes:
  - `admin.ai-flows.index`
  - `admin.ai-flows.create`
  - `admin.ai-flows.edit`
  - `admin.ai-flows.versions.index`
  - `admin.ai-flows.versions.show`
- Added Livewire 2 components:
  - `App\Http\Livewire\Admin\AiFlows\AiFlowIndex`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowForm`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowVersionIndex`
  - `App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow`
- Added Blade wrapper views under `resources/views/admin/ai-flows/`.
- Added Livewire views under `resources/views/livewire/admin/ai-flows/`.
- Added `Flujos IA` to desktop and responsive admin navigation with active state `admin.ai-flows*`.
- Added feature tests for routes, admin-only access, create/edit flow, duplicate key validation, draft version creation, Spanish status labels, publication error display, successful publication and Content Management regression smoke coverage.

### Rules implemented
- Only Administrador users can access the module.
- Flow creation validates required `name`, required unique `key`, and lowercase key without spaces or accents.
- Flow editing updates `name`, `description` and `is_active`; deletion is not included.
- Version creation uses the next incremental `version_number`.
- Version publication is delegated to `AiFlowVersionService`.
- Publication errors/warnings are displayed locally in Spanish.
- Only one version remains `published` per flow after publication.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (39 tests, 105 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 2A parser and publication rules)

### Context
- Scope limited to the core parser, publication validation and publication service for the generic `Flujos IA` module.
- No UI, Livewire components, dynamic forms, client execution flow, final prompt generation for executions, market research seeders or Content Management migration were implemented.

### Changes made
- Added `App\Services\AiFlows\AiFlowVariableParser`.
- Added `App\Services\AiFlows\AiFlowVersionValidationService`.
- Added `App\Services\AiFlows\AiFlowVersionService`.
- Added automated tests for parser, version validation and publication rules.

### Parser rules implemented
- Detects placeholders using `{{nombre_variable}}`.
- Accepts only snake_case variable names:
  - lowercase
  - starts with a letter
  - numbers allowed after letters
  - underscores allowed
  - no spaces, accents, uppercase or special characters
- Keeps valid variables in first-appearance order.
- Ignores duplicate valid variables.
- Reports invalid tokens, including empty placeholders.

### Publication rules implemented
- Version must be `draft`.
- Version must have at least one active step.
- Active steps must have non-empty `base_prompt`.
- Valid placeholders must have configured variables.
- Invalid prompt tokens block publication.
- Configured unused variables generate warnings, not errors.
- Output variables require `source_step_id`.
- Output variables and step dependencies must reference steps from the same version.
- Publication runs inside a DB transaction.
- Previous `published` versions of the same flow are archived so only one remains published.
- `published_at` and `published_by` are recorded.
- A basic edit-protection method blocks editing published versions with historical executions.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (27 tests, 71 assertions)` for AiFlows
  - `OK (101 tests, 495 assertions)` for Content Management
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-14 (ai flows phase 1 persistence)

### Context
- Scope limited to Phase 1 of the generic `Flujos IA` module.
- Implemented only persistence base, Eloquent models, relationships, constants, labels, admin-only access service and structural tests.
- No visual UI, Livewire administration components, flow execution UI, dynamic forms, prompt rendering, parser, market research seeder or Content Management migration were implemented.

### Changes made
- Added migration:
  - `database/migrations/2026_07_14_120000_create_ai_flows_module_tables.php`
- Added tables:
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
- Added Eloquent models and relationships for the new tables.
- Added `Empresa` and `User` relationships into the new AI Flows module.
- Added `App\Services\AiFlows\AiFlowAccessService` with admin-only MVP access.
- Added `App\Support\AiFlowLabels` for Spanish labels.
- Added structural model validation for:
  - snake_case variable names without spaces or accents
  - global variables not attached to steps
  - output source step usage only for output variables
  - variable step/source step version consistency
  - step dependency version consistency
- Kept `blocked` as a future derived visual state, not persisted.
- Kept the one-current-strategic-output rule as a future transactional service responsibility, not a MySQL partial unique index.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\AiFlows`
- Result:
  - `OK (8 tests, 32 assertions)`
- Content Management regression executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Content Management result:
  - `OK (101 tests, 495 assertions)`
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-13 (content management curation stepper item)

### Context
- Scope limited to integrating the existing manual curation block into the sticky stepper of the Content Management operational detail.
- No database schema, internal states, services, prompt generation, master templates, Livewire events, Step 3 availability rules, final files, delivery or publication behavior were changed.

### Changes made
- Added a sticky stepper item:
  - label: `Curado`
  - target: `#content-step-curation`
  - visible badge: `Manual`
- `Manual` is only a visual label and is not persisted as a state.
- Kept the curation block content unchanged.
- Kept mobile horizontal scrolling behavior through the existing stepper layout.
- Extended detail tests to cover the new stepper link, visual `Manual` label and existing curation anchor.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement\ContentArticleObjectiveDetailTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (13 tests, 160 assertions)` for the detail suite
  - `OK (101 tests, 495 assertions)` for the full Content Management suite
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-13 (content management manual curation block)

### Context
- Scope limited to adding a visual/manual curation block before Step 3 in the Content Management operational detail.
- No internal states, database schema, services, prompt generation, master templates, Livewire events, final files, delivery/publication behavior or Step 3 availability rules were changed.

### Changes made
- Added a static operational block after Step 2 and before Step 3.
- Added stable future anchor:
  - `content-step-curation`
- Added recommended GPT:
  - `@CuradorDeContenido`
- Added grouped manual instructions:
  - open `@CuradorDeContenido` in ChatGPT
  - attach the final Word/PDF article document
  - request review of clarity, coherence, precision, tone and strategic alignment
  - apply recommended changes to the final document
  - use the curated document as input for Step 3
- Added warning:
  - `Este paso es manual y no reemplaza la revisión humana final.`
- Did not add the curation block to the sticky stepper.
- Extended detail tests to cover placement, full content, stable anchor and no stepper link.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement\ContentArticleObjectiveDetailTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (13 tests, 157 assertions)` for the detail suite
  - `OK (101 tests, 492 assertions)` for the full Content Management suite
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-13 (content management clean Prompt 3 copy text)

### Context
- Scope limited to the generated/copyable Prompt 3 content in Content Management.
- No visual design, recommended-GPT placement, visual Step 3 instructions, loaders, local messages, soft backgrounds, sticky stepper, states, persistence schema, final files, delivery or publication behavior were changed.

### Root cause
- The phrase `Adjunta en ChatGPT el documento final del articulo en formato Word o PDF antes de ejecutar este prompt.` was not part of the approved Prompt 3 TXT template.
- It was prepended by `ContentVideoInstagramPromptService` before the active template body during prompt assembly, so every new Prompt 3 generation stored and copied that instruction.

### Changes made
- Removed the visual attachment instruction from Prompt 3 assembly.
- Prompt 3 now starts with the active master template body.
- Kept the minimal article context and non-invention warning in the generated prompt.
- Removed the unused `attachmentInstruction` render parameter from the Livewire panel.
- Kept the visual UI instruction inside the `@StorytellingCorporativo` recommended-GPT block:
  - `Adjunta primero el documento final del artículo en Word o PDF.`
- No template source file, seeder or data migration was changed because the offending phrase was not in the master template source.
- Existing historical generations were not rewritten.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement\ContentArticleVideoInstagramPanelTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (14 tests, 43 assertions)` for the Prompt 3 suite
  - `OK (100 tests, 473 assertions)` for the full Content Management suite
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-13 (content management recommended GPT instruction placement)

### Context
- Scope limited to reorganizing the recommended-GPT instruction blocks in the Content Management operational detail.
- No business logic, services, persistence, internal states, prompt generation, Livewire loaders, local messages, sticky stepper, soft backgrounds, final files, delivery or publication logic were changed.

### Changes made
- Moved the recommended-GPT block to the start of each operational prompt step, before the generated prompt area:
  - Step 1: `@consultormarketingdigital`
  - Step 2: `@redactorSEOGutenber`
  - Step 3: `@StorytellingCorporativo`
- Kept the GPT names as plain text; no links or invented URLs were added.
- Consolidated Step 3 instructions into the single recommended-GPT block.
- Removed the separate Step 3 Word/PDF warning block to avoid duplicate visual instructions.
- Added test coverage for GPT block ordering, absence of invented links and single Word/PDF instruction rendering.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement\ContentArticleObjectiveDetailTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (12 tests, 138 assertions)` for the detail suite
  - `OK (99 tests, 465 assertions)` for the full Content Management suite
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-13 (content management local action feedback and loaders)

### Context
- Scope limited to confirmation/error/blocking messages and loading indicators in the Content Management operational detail.
- No business logic, services, persistence, internal states, prompt generation, GPT recommended hints, soft backgrounds, sticky stepper or flow structure were changed.

### Root cause
- Some actions used shared or card-level session flashes that were not close enough to the action that triggered them.
- Delivery and publication shared one success flash key, so feedback could appear above both cards instead of in the specific card.
- Operational buttons did not expose Livewire action loading states, making processing and double-click prevention unclear.

### Changes made
- Split success flash keys by card/action:
  - Prompt 1 generation
  - Step 1 refined fields
  - Step 1 ready
  - Prompt 2 generation
  - Step 2 ready
  - Prompt 3 generation
  - Step 3 ready
  - final file upload
  - delivery actions
  - publication actions
- Rendered success/error/blocking/warning messages inside the card or block where the action happens.
- Added Livewire 2 loading indicators using:
  - `wire:loading.flex`
  - action-specific `wire:target`
  - `style="display: none;"`
  - inline SVG spinner
- Added disabled-on-loading behavior for relevant action buttons.
- Kept existing business validation and blocking rules unchanged.
- Extended tests for local messages, hidden-by-default loading markup and action coverage.

### Actions covered
- Save refined results
- Mark Step 1 ready
- Generate Prompt 1
- Generate Prompt 2
- Mark Step 2 ready
- Generate Prompt 3
- Mark Step 3 ready
- Upload final file
- Mark delivered
- Unmark delivery
- Publish
- Update published URL

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement\ContentArticleObjectiveDetailTest.php tests\Feature\ContentManagement\ContentArticleDraftingPanelTest.php tests\Feature\ContentManagement\ContentArticleVideoInstagramPanelTest.php tests\Feature\ContentManagement\ContentArticleFinalFilePanelTest.php tests\Feature\ContentManagement\ContentArticleDeliveryPublicationPanelTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (11 tests, 124 assertions)` for focused operational-detail suites
  - `OK (98 tests, 451 assertions)` for the full Content Management suite
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-13 (content management soft section backgrounds)

### Context
- Scope limited to minor visual adjustments in the Content Management operational detail.
- No business logic, services, persistence, Livewire events, prompt generation, internal states, GPT recommended hints, action messages or flow structure were changed.

### Changes made
- Updated the operational detail header subtitle from dark emerald text to white text for stronger contrast over the green header.
- Applied soft background differentiation to the main flow blocks:
  - Step 1: light blue
  - Step 2: light emerald
  - Step 3: light indigo
  - Final files: light amber
  - Manual delivery: light slate
  - Manual publication: light emerald
- Kept existing borders, shadows, spacing, badges, buttons, textareas and internal panels readable.
- Updated Content Management detail assertions to cover the new contrast and soft-background classes.

### Test execution
- First execution failed because local MySQL was not running:
  - `SQLSTATE[HY000] [2002] No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión`
- Started local Laragon MySQL and executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (97 tests, 427 assertions)`
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-10 (content management collapsible prompt histories)

### Context
- Scope limited to making prompt generation histories collapsible in the Content Management operational detail.
- No business logic, services, persistence, states, Livewire events, sticky stepper, GPT recommended hints or flow rules were changed.

### Changes made
- Wrapped the Prompt 1, Prompt 2 and Prompt 3 generation histories in native `<details>/<summary>` blocks.
- Histories are closed by default and keep all existing generation information and actions available when expanded.
- Summary labels show the real generation count for each step:
  - `Historial de generaciones (N)`
- Kept empty-history messages visible inside the collapsible block when no generations exist.
- Extended detail tests to cover counts for Prompt 1, Prompt 2 and Prompt 3, native collapsible markup and existing prompt actions.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (97 tests, 421 assertions)`
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-10 (content management sticky operational stepper)

### Context
- Scope limited to a sticky stepper navigation in the Content Management operational detail.
- No business logic, services, persistence, internal states, Livewire events, master templates, GPT recommended hints or histories were changed.

### Changes made
- Added a sticky anchor stepper to the operational detail.
- Stepper entries:
  - Objetivo y público
  - Redacción
  - Video e Instagram
  - Archivo final
  - Entrega / Publicación
- Added stable section anchors:
  - `content-step-objective`
  - `content-step-drafting`
  - `content-step-video-instagram`
  - `content-step-final-file`
  - `content-step-release`
- Added scroll offset classes so anchored navigation does not hide card starts under the sticky bar.
- Stepper state is derived from existing article, step, file, delivery and publication data.
- Updated detail tests to assert sticky navigation, anchors, Spanish visible states and responsive horizontal scrolling.
- Updated architecture documentation with the visual sticky stepper pattern.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (97 tests, 414 assertions)`
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-10 (content management recommended GPT hints)

### Context
- Scope limited to visual integration of recommended GPT names in each Content Management operational step.
- No business logic, services, persistence, states, events, master templates, navigation or histories were changed.

### Changes made
- Added compact recommended-GPT hint near the generated prompt in Step 1:
  - `@consultormarketingdigital`
- Added compact recommended-GPT hint near the generated prompt in Step 2:
  - `@redactorSEOGutenber`
- Added compact recommended-GPT hint near the generated prompt in Step 3:
  - `@StorytellingCorporativo`
- Step 3 includes the required operator sequence:
  - open the GPT in ChatGPT
  - attach the final Word/PDF document first
  - paste the generated prompt
  - execute the query
- No URLs were added because no URL configuration exists for these GPTs.
- Extended detail tests to assert the three GPT names, Step 3 sequence and absence of invented links.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (97 tests, 398 assertions)`

## 2026-07-10 (content management detail visual hierarchy)

### Context
- Scope limited to visual hierarchy and contrast in the Content Management operational detail.
- No business logic, services, persistence, states, Livewire events or navigation were changed.

### Changes made
- Improved header subtitle contrast in:
  - `resources/views/admin/content-management/show.blade.php`
- Improved title hierarchy for operational cards:
  - Step 1
  - Step 2
  - Step 3
  - final files
  - manual delivery
  - manual publication
- Standardized top status badges with compact padding, stronger text contrast and Spanish labels.
- Removed repeated step status from the inner `Estado del paso` detail block while keeping:
  - `Marcado por`
  - `Fecha`
- Extended Content Management detail assertions to cover contrast classes, title hierarchy and badge styling.

### Test execution
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (97 tests, 387 assertions)`
- Environment note:
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`, but tests pass.

## 2026-07-10 (content management detail reactivity and local feedback)

### Context
- Production showed stale state in the Content Management article detail:
  - Step 1 refined fields persisted correctly.
  - Step 2 did not immediately reflect updated refined fields or objective ready state.
  - Users had to leave the detail and reopen the article before generating Prompt 2.
  - Success/error feedback appeared too far from the action in long scroll views.
- Scope limited to Livewire reactivity and per-card feedback placement.
- Business rules, templates, internal states and XLSX import were not changed.

### Root cause
- Step 1, Step 2 and Step 3 are separate Livewire child components.
- Step 2 and Step 3 were mounted with stable keys and only refreshed when their own component received an action or the page reloaded.
- Step 1 state changes did not emit any event that Step 2 listened to, so Step 2 availability stayed stale in the browser.
- Step feedback used session flashes rendered above the card, which made messages hard to see on long detail pages.

### Changes made
- Updated Step 1 component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleObjectiveDetail.php`
  - emits `contentObjectiveUpdated` after relevant Step 1 changes
- Updated Step 2 component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleDraftingPanel.php`
  - listens to `contentObjectiveUpdated` via Livewire 2 `$listeners`
  - re-renders from the database on objective updates
  - emits `contentDraftingUpdated` after drafting changes
- Updated Step 3 component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleVideoInstagramPanel.php`
  - listens to `contentDraftingUpdated` via Livewire 2 `$listeners`
- Updated card views:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
  - `resources/views/livewire/admin/content-management/content-article-drafting-panel.blade.php`
  - `resources/views/livewire/admin/content-management/content-article-video-instagram-panel.blade.php`
- Extended tests:
  - `tests/Feature/ContentManagement/ContentArticleObjectiveDetailTest.php`
  - `tests/Feature/ContentManagement/ContentArticleDraftingPanelTest.php`
  - `tests/Feature/ContentManagement/ContentArticleVideoInstagramPanelTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`

### Validation
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement\ContentArticleObjectiveDetailTest.php tests\Feature\ContentManagement\ContentArticleDraftingPanelTest.php tests\Feature\ContentManagement\ContentArticleVideoInstagramPanelTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\ContentManagement`
- Result:
  - `OK (11 tests, 60 assertions)`
  - `OK (97 tests, 379 assertions)` for the full Content Management feature suite
- Environment note:
  - MySQL local had to be started before running DB-backed tests.
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`.

## 2026-07-09 (security incident - production APP_KEY re-encryption tooling)

### Context
- The exposed Laravel `APP_KEY` was confirmed by operations to match the effective production key.
- Existing encrypted production data was validated as currently readable before this phase:
  - `form_notification_public_links.token_encrypted`
  - `empresa_whatsapp_settings.whatsapp_access_token`
  - `empresa_integrations.meta_json.google_refresh_token_encrypted`
- Scope limited to creating a safe, auditable re-encryption tool before activating a new key.
- Production `APP_KEY` was not changed.
- No `php artisan key:generate` was executed.
- Git history cleanup was not performed.
- No secret values or plaintext were documented.

### Changes made
- Added Artisan command:
  - `app/Console/Commands/ReencryptAppKeyEncryptedData.php`
- Added automated tests:
  - `tests/Feature/Security/ReencryptAppKeyEncryptedDataCommandTest.php`
- Updated testing bootstrap:
  - `tests/CreatesApplication.php`
  - allows explicit SQLite testing override to skip MySQL schema provisioning when a runtime supports it; default project testing remains MySQL-based.
- Updated technical documentation:
  - `ARCHITECTURE.md`

### Command behavior
- Command:
  - `security:reencrypt-app-key-data`
- Dry-run is the default.
- Apply mode requires:
  - `--apply`
- Production execution requires:
  - `--confirm-production`
- New key input is external to repository and command history:
  - temporary env var `QITS_NEW_APP_KEY` by default
  - or private file outside the repository with `--new-key-file`
- Source/decrypt key input defaults to the current runtime `APP_KEY`, but can be overridden for rollback with:
  - `--source-key-env`
  - `--source-key-file`
- The command uses explicit old/new Laravel encrypters and raw DB writes to avoid accidental Eloquent encrypted-cast re-encryption with the active app key.
- Apply mode runs in one DB transaction and aborts on any decrypt/encrypt/verification failure.
- Output and logs include counts and technical table/id/field context only; no plaintext is printed.

### Validation
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor\bin\phpunit tests\Feature\Security\ReencryptAppKeyEncryptedDataCommandTest.php`
- Result:
  - `OK (7 tests, 30 assertions)`
- Local environment notes:
  - MySQL had to be started locally before DB-backed tests could run.
  - PHP 8.1 has `pdo_mysql`.
  - PHP 8.1 does not currently have `pdo_sqlite`.
  - PHP startup still warns about missing `oci8_12c` and `pdo_firebird`.

### Rollback guidance
- Take a database backup before running apply mode in production.
- Do not activate the new `APP_KEY` until dry-run and apply counts are reviewed.
- If rollback is needed before activation, restore the backup or run the inverse re-encryption with the old/new key roles swapped through the source-key options.
- Do not rely on plaintext exports for rollback.
- Keep the old key secured until post-rotation validation and rollback window are closed.

## 2026-07-09 (security incident - exposed testing APP_KEY)

### Context
- GitGuardian reported a Laravel `APP_KEY` exposed in `.env.testing` on GitHub.
- Scope limited to assessing repository exposure, containing current tracking, and preparing safe testing configuration.
- Production key rotation and Git history rewriting were intentionally not performed in this phase.

### Findings
- `.env.testing` was tracked in Git before containment.
- `.env.testing` was introduced by commit:
  - `2996ca090e559a74e679d96d093c93251caadfb6`
- Tracked `.env*` files after containment:
  - `.env.example`
- `.env.example` contains empty placeholders/references only for inspected sensitive keys.
- Local untracked files `.env` and `.env.production` exist in the workspace and share the same `APP_KEY` fingerprint as the exposed `.env.testing`; this indicates local reuse, but does not by itself prove the deployed production environment uses that key.

### Containment actions
- Removed `.env.testing` from Git tracking while keeping the local file intact.
- Updated `.gitignore` to ignore `.env.*` by default and allow only safe example files.
- Added `.env.testing.example` with no real secrets.
- Updated `tests/CreatesApplication.php` so tests prepare a non-secret runtime `APP_KEY` when `APP_ENV=testing`.
- Updated `ARCHITECTURE.md` with the safe testing environment strategy.

### Validation
- Confirmed `.env.testing` no longer appears in `git ls-files`.
- Confirmed tracked `.env*` files are limited to `.env.example`.
- Executed:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (93 tests, 363 assertions)`

### Not performed
- No production `APP_KEY` rotation.
- No `php artisan key:generate` against production.
- No Git history rewrite.
- No secret values were documented.

## 2026-07-08

### Context
- First implementation phase for Content Management module.
- Scope limited to persistence, Eloquent models, structural state rules and technical documentation.

### Changes made
- Added migration:
  - `database/migrations/2026_07_08_120000_create_content_management_module_tables.php`
- Added models:
  - `app/Models/ContentImport.php`
  - `app/Models/ContentArticle.php`
  - `app/Models/ContentArticleStep.php`
  - `app/Models/ContentMasterTemplate.php`
  - `app/Models/ContentMasterTemplateVersion.php`
  - `app/Models/ContentArticleGeneration.php`
  - `app/Models/ContentArticleFile.php`
- Extended existing models:
  - `app/Models/Empresa.php`
  - `app/Models/User.php`
- Updated architecture documentation:
  - `ARCHITECTURE.md`

### Functional rules captured in code
- Articles belong obligatorily to imports.
- Tenant resolution for articles is indirect through import -> empresa.
- Three fixed content steps:
  - `objective`
  - `drafting`
  - `video_instagram`
- Allowed step states:
  - `pending`
  - `in_progress`
  - `ready`
- Allowed main statuses:
  - `processing`
  - `unpublished`
  - `published`
- Allowed operational stages:
  - `pending`
  - `strategic_refinement`
  - `drafting`
  - `video_instagram`
  - `final_file`
  - `completed`
- Allowed tones:
  - `tuteo`
  - `usteo`
- Only one active template version is allowed per master template at model/business-rule level.
- `objective` cannot move to `ready` unless refined objective and refined target audience are complete.

### Pending for later phases
- Livewire UI
- XLSX import processing
- Prompt-generation services
- File upload flows
- Delivery/publication workflows

## 2026-07-08 (final adjustments)

### Changes made
- Removed explicit redundant FK indexes from content module migration:
  - `content_imports.empresa_id`
  - `content_articles.content_import_id`
- Shortened long foreign key names in migration to stay within MySQL identifier limits:
  - `cmtv_template_fk`
  - `cag_template_version_fk`
- Kept MVP rule:
  - one final physical file per article version via `UNIQUE(content_article_id, version_number)`
- Updated `ARCHITECTURE.md`:
  - current PHP runtime target is 8.1
  - documented that model guards apply to Eloquent writes and do not fully protect direct SQL / Query Builder / external concurrency
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentManagementModelRulesTest.php`

### Covered rules
- Prevent second active version for the same template
- Allow active versions in different templates
- Prevent `objective -> ready` without refined fields
- Allow `objective -> ready` with both refined fields complete

## 2026-07-08 (phase 2 initial xlsx support)

### Changes made
- Added dependency:
  - `phpoffice/phpspreadsheet` `1.30.5`
- Added service:
  - `app/Services/ContentManagement/ContentXlsxImportService.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentXlsxImportServiceTest.php`

### Implemented behavior
- Accepts only `.xlsx`
- Stores uploaded file temporarily in local storage
- Validates the full workbook before any persistence
- Rejects partial import when any row has validation errors
- Deletes the temporary XLSX after processing
- Detects duplicates by:
  - empresa
  - fecha
  - tema normalizado
- Creates one `content_imports` record and the related `content_articles` only on fully valid import
- Initializes the three content steps per imported article

### Validation covered by tests
- Valid XLSX
- Missing required header
- Invalid date
- Empty required field
- Invalid row prevents all persistence
- Existing duplicate prevents persistence

## 2026-07-08 (phase 2 ui import xlsx)

### Changes made
- Added route:
  - `routes/web.php` -> `admin.content-management.imports`
- Added Livewire UI:
  - `app/Http/Livewire/Admin/ContentManagement/ContentImportManager.php`
- Added views:
  - `resources/views/admin/content-management/imports.blade.php`
  - `resources/views/livewire/admin/content-management/content-import-manager.blade.php`
  - `resources/views/livewire/admin/content-management/partials/import-validation-summary.blade.php`
- Extended service:
  - `app/Services/ContentManagement/ContentXlsxImportService.php`
  - preview/confirm flow over staged temporary XLSX files
  - duplicate count in validation result
  - protected persistence hooks to cover transactional rollback in tests

### Implemented behavior
- Authorized empresa list is resolved from current access rules:
  - admin users see all empresas
  - non-admin users only see their `empresa_id`
  - users without authorized empresa are blocked in component mount
- Tone selection is mandatory before preview or import
- Validation stays in `ContentXlsxImportService`; Livewire does not duplicate XLSX business rules
- If preview finds any error, confirmation is blocked
- Definitive confirmation persists:
  - one `content_imports`
  - related `content_articles`
  - three `content_article_steps` per article
- Temporary staged XLSX is deleted on confirm/cancel paths handled by the component

### Tests added or extended
- Added:
  - `tests/Feature/ContentManagement/ContentImportManagerMountTest.php`
- Extended:
  - `tests/Feature/ContentManagement/ContentXlsxImportServiceTest.php`

### Validation covered by tests
- Mount blocks users without authorized empresa
- Mount loads all empresas for admin users
- Mount limits non-admin users to their visible empresa
- Persistence failure inside transactional import rolls back all created rows

## 2026-07-09

### Context
- Targeted correction for the Content Management XLSX import UI phase.
- Scope limited to route/Livewire diagnostics, orphaned temp XLSX cleanup, tests and documentation.

### Changes made
- Fixed route rendering for:
  - `GET /admin/content-management/imports`
- Updated wrapper view:
  - `resources/views/admin/content-management/imports.blade.php`
  - mounts the Livewire component by class reference instead of alias
- Extended content import service:
  - `app/Services/ContentManagement/ContentXlsxImportService.php`
  - added temp disk/directory accessors
  - added temp file purge method
- Added config:
  - `config/content_management.php`
- Added artisan command:
  - `app/Console/Commands/PruneContentManagementTempImports.php`
- Updated scheduler:
  - `app/Console/Kernel.php`
- Extended automated tests:
  - `tests/Feature/ContentManagement/ContentImportManagerMountTest.php`
  - `tests/Feature/ContentManagement/ContentXlsxImportServiceTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Confirmed root cause
- The route existed in `route:list`.
- The failure was caused by Livewire component alias resolution through a stale cached manifest:
  - `bootstrap/cache/livewire-components.php`
- The new component alias for Content Management import UI was not present there, so the route view could not resolve the component reliably through the alias tag.

### Implemented behavior
- Route wrapper no longer depends on the cached Livewire alias manifest for this screen.
- Orphaned temp XLSX purge deletes only files under:
  - `tmp/content-management/imports`
- Purge threshold is configurable and defaults to a safe TTL window.

### Test execution note
- Specific new tests were prepared for route access and temp-file purge.
- Automated execution remains environment-dependent; no migration-destructive command was used.

## 2026-07-09 (testing environment fix)

### Context
- Scope limited to unblocking PHPUnit execution for `tests/Feature/ContentManagement` with PHP 8.1.

### Root cause confirmed
- `phpunit.xml` only set `APP_ENV=testing`; it did not provide an isolated database connection.
- `.env.testing` did not exist, so PHPUnit inherited the local `.env` MySQL credentials.
- The inherited local user `qits_app` was not authenticating successfully from the PHP 8.1 CLI runtime.
- PHP 8.1 CLI in this environment does not have `pdo_sqlite`, so SQLite was not a viable fallback for `RefreshDatabase`.
- Artisan/exception logging could also fail against `storage/logs/laravel.log`, so tests needed a non-file log channel.

### Changes made
- Added `.env.testing` with isolated test configuration:
  - base `DB_DATABASE=qits_app_testing`
  - `DB_USERNAME=root`
  - `DB_PASSWORD=`
  - `LOG_CHANNEL=stderr`
  - array/sync drivers for cache, session, queue and mail
- Added runtime schema isolation in:
  - `tests/CreatesApplication.php`
  - each PHPUnit process now derives and recreates its own MySQL schema as `qits_app_testing_{pid|TEST_TOKEN}` before Laravel boots
- Updated `ARCHITECTURE.md` to document the current test runtime assumptions.

## 2026-07-09 (content management main listing)

### Context
- Scope limited to the main article listing for Content Management.
- No prompt generation, final files or publication workflow were implemented in this phase.

### Changes made
- Added service:
  - `app/Services/ContentManagement/ContentAccessService.php`
- Added Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleIndex.php`
- Added views:
  - `resources/views/admin/content-management/index.blade.php`
  - `resources/views/livewire/admin/content-management/content-article-index.blade.php`
  - `resources/views/admin/content-management/show.blade.php`
- Updated routes:
  - `routes/web.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentArticleIndexTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Main route:
  - `GET /admin/content-management`
- Minimal detail route:
  - `GET /admin/content-management/articles/{article}`
- Search by:
  - article topic
  - empresa name
- Combined filters by:
  - empresa
  - main status
  - period (`all`, current month, previous month, next month)
- Priority order:
  - processing
  - unpublished current month
  - other unpublished
  - published
- Internal sort:
  - processing by latest update
  - unpublished by article date ascending
  - published by published date descending
- Action labels:
  - `Generar` for pending stage
  - `Continuar` once the flow has already started
- Access control:
  - admin users can see all visible empresas under current rules
  - non-admin users are restricted server-side to their own empresa articles
  - article detail access is revalidated server-side and returns `403` outside the tenant scope

### Validation covered by tests
- Multiempresa isolation
- Search by topic
- Search by empresa
- Combined filters
- Priority ordering
- Pagination
- Authorized access
- Forbidden access to another empresa article

## 2026-07-09 (content master templates bootstrap)

### Context
- Scope limited to the initial registration of the 3 approved master templates for Content Management.
- No prompt execution, operator UI or OpenAI integration was implemented in this phase.

### Changes made
- Copied approved source files into repository seed data:
  - `database/seeders/data/content-management/1_GENERACION OBJETIVO ARTICULO.txt`
  - `database/seeders/data/content-management/2_REDACCION ARTICULO.txt`
  - `database/seeders/data/content-management/3_GUION VIDEOS E INSTAGRAM.txt`
- Added seeder:
  - `database/seeders/ContentMasterTemplatesSeeder.php`
- Updated bootstrap seeding chain:
  - `database/seeders/DatabaseSeeder.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentMasterTemplatesSeederTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Registers exactly these master template keys:
  - `objective`
  - `drafting`
  - `video_instagram`
- Creates initial version:
  - `version_number = 1`
  - `is_active = true`
- Marks each master template:
  - `is_active = true`
- Idempotent behavior:
  - rerunning the seeder does not duplicate templates or versions
  - existing version `1` is reused only when its `template_body` matches the approved file exactly
- Protection:
  - if version `1` already exists with different content, the seeder throws and does not overwrite history
  - if another active version would conflict with initial activation, the seeder throws instead of changing activation silently

### Validation covered by tests
- Exactly 3 expected keys exist
- Each key has one active version
- Stored `template_body` matches the copied approved source file
- Re-execution is idempotent
- Conflicting historical version `1` is rejected

## 2026-07-09 (content management objective operational flow)

### Context
- Scope limited to the operational detail for step `objective`.
- No Prompt 2, Prompt 3, final files or publication workflow were implemented in this phase.

### Changes made
- Added service:
  - `app/Services/ContentManagement/ContentObjectivePromptService.php`
- Added Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleObjectiveDetail.php`
- Added Livewire view:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
- Updated detail wrapper:
  - `resources/views/admin/content-management/show.blade.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentArticleObjectiveDetailTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Detail screen now shows:
  - empresa
  - fecha
  - tema
  - objetivo general
  - publico objetivo general
  - objetivo refinado
  - publico refinado
  - estado principal
  - etapa operativa
  - estado del paso `objective`
- Prompt 1 generation:
  - uses the active master template version for key `objective`
  - stores one independent `content_article_generations` row per generate/regenerate click
  - keeps the exact final prompt text persisted per generation
- First objective generation updates:
  - `main_status = processing`
  - `operational_stage = strategic_refinement`
  - objective step `step_status = in_progress`
- Regeneration does not reset states when a prior objective generation already exists.
- Manual refinement save updates only:
  - `refined_objective`
  - `refined_target_audience`
- Mark ready:
  - requires both refined fields complete
  - stores `ready_by` and `ready_at`
- Sensitive actions revalidate tenant access server-side via `ContentAccessService`.

### Validation covered by tests
- Uses exactly the active objective template version
- Regeneration preserves independent history rows
- First generation applies the expected state transition
- Later generations do not reset article/step state
- Cross-tenant article tampering is blocked on generate
- Saving refined fields preserves general source fields
- Ready is blocked without both refined fields
- Ready is allowed with both refined fields and records audit fields

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleObjectiveDetailTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (8 tests, 31 assertions)` for the new objective flow suite
  - `OK (38 tests, 140 assertions)` for the full Content Management feature suite

## 2026-07-09 (content management drafting operational flow)

### Context
- Scope limited to Prompt 2 / drafting.
- No Prompt 3, final files or publication workflow were implemented in this phase.

### Changes made
- Added service:
  - `app/Services/ContentManagement/ContentDraftingPromptService.php`
- Added Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleDraftingPanel.php`
- Added Livewire view:
  - `resources/views/livewire/admin/content-management/content-article-drafting-panel.blade.php`
- Extended operational detail view:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentArticleDraftingPanelTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Drafting generation is blocked unless:
  - objective step is `ready`
  - `refined_objective` exists
  - `refined_target_audience` exists
  - `EmpresaSeoProperty.site_url` exists and is non-empty
- URL resolution for Prompt 2 uses only:
  - `content_article -> content_import -> empresa -> seoProperty -> site_url`
- No fallback is used from:
  - `wordpress_site_url`
  - `ProyectoEmpresa.url`
- Each generate/regenerate action creates one independent `content_article_generations` row for `step_type = drafting`.
- First drafting generation updates:
  - `main_status = processing`
  - `operational_stage = drafting`
  - drafting step `step_status = in_progress`
- Mark drafting ready:
  - requires at least one drafting generation
  - stores `ready_by` and `ready_at`
  - moves `operational_stage` to `video_instagram`
  - keeps `main_status = processing`

### Validation covered by tests
- Blocks drafting if objective is not ready
- Blocks drafting if refined fields are missing
- Blocks drafting if `EmpresaSeoProperty.site_url` is missing
- Does not use `wordpress_site_url` as fallback
- Does not use `ProyectoEmpresa.url` as fallback
- Uses exactly the active drafting template version
- Includes URL, topic, refined fields and tone in the final prompt
- Preserves independent drafting history rows
- First drafting generation applies the expected state transition
- Drafting regeneration does not reset states
- Cross-tenant article tampering is blocked on generate
- Ready is blocked without a drafting generation
- Ready is allowed with generation and records audit fields

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleDraftingPanelTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (12 tests, 39 assertions)` for the new drafting flow suite
  - `OK (50 tests, 179 assertions)` for the full Content Management feature suite

## 2026-07-09 (content management video_instagram operational flow)

### Context
- Scope limited to Prompt 3 / video_instagram.
- No final file upload, delivery or publication workflow was implemented in this phase.

### Changes made
- Added service:
  - `app/Services/ContentManagement/ContentVideoInstagramPromptService.php`
- Added Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleVideoInstagramPanel.php`
- Added Livewire view:
  - `resources/views/livewire/admin/content-management/content-article-video-instagram-panel.blade.php`
- Extended operational detail view:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentArticleVideoInstagramPanelTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Video Instagram generation is blocked unless:
  - drafting step is `ready`
  - at least one drafting generation already exists
- Prompt 3 generation:
  - uses exactly the active master template version for key `video_instagram`
  - prepends an explicit operator instruction to attach the final article document in Word or PDF before execution in ChatGPT
  - includes only minimal context (`topic`)
  - does not inject or simulate the final article content
- Each generate/regenerate action creates one independent `content_article_generations` row for `step_type = video_instagram`.
- First video_instagram generation updates:
  - `main_status = processing`
  - `operational_stage = video_instagram`
  - video_instagram step `step_status = in_progress`
- Mark video_instagram ready:
  - requires at least one video_instagram generation
  - stores `ready_by` and `ready_at`
  - moves `operational_stage` to `final_file`
  - keeps `main_status = processing`

### Validation covered by tests
- Blocks video_instagram if drafting is not ready
- Blocks video_instagram if no drafting generation exists
- Uses exactly the active video_instagram template version
- Includes explicit Word/PDF attachment instruction
- Does not invent final article content
- Preserves independent video_instagram history rows
- First video_instagram generation applies the expected state transition
- Video_instagram regeneration does not reset states
- Cross-tenant article tampering is blocked on generate
- Ready is blocked without a video_instagram generation
- Ready is allowed with generation and records audit fields

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleVideoInstagramPanelTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (11 tests, 31 assertions)` for the new video_instagram flow suite
  - `OK (61 tests, 210 assertions)` for the full Content Management feature suite

## 2026-07-09 (content management final file upload and versioning)

### Context
- Scope limited to manual final file upload, version history, secure download and post-final-file state transition.
- No delivery or publication workflow was implemented in this phase.

### Changes made
- Added service:
  - `app/Services/ContentManagement/ContentFinalFileService.php`
- Added Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleFinalFilePanel.php`
- Added download controller:
  - `app/Http/Controllers/Admin/ContentManagement/ContentArticleFileDownloadController.php`
- Added Livewire view:
  - `resources/views/livewire/admin/content-management/content-article-final-file-panel.blade.php`
- Extended operational detail view:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
- Updated routes:
  - `routes/web.php`
- Updated content management config:
  - `config/content_management.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentArticleFinalFilePanelTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Upload is blocked unless:
  - `video_instagram` step is `ready`
  - article `operational_stage` is `final_file` or `completed`
- Accepted formats:
  - `.docx`
  - `.pdf`
- Validation includes:
  - allowed extension
  - coherent MIME/signature
  - non-empty file
  - configurable max size
- Storage strategy:
  - Laravel `Storage`
  - private internal path under `content-management/final-files/article_{id}`
  - generated physical filename per version
  - original filename preserved only as metadata
- Versioning strategy:
  - one new `content_article_files` row per upload
  - sequential `version_number`
  - no overwrite of previous versions
  - transaction + `lockForUpdate()` on the article row to reduce concurrent version collisions
- Secure download:
  - authenticated route
  - server-side tenant validation through `ContentAccessService`
  - no `file_path` exposure in UI
- First valid final file upload transitions the article to:
  - `operational_stage = completed`
  - `main_status = unpublished`
  - `delivered_at = null`
  - `published_at = null`
- If file storage succeeds but DB persistence fails, the stored file is deleted.

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleFinalFilePanelTest.php --filter test_accepts_valid_pdf_upload`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleFinalFilePanelTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (1 test, 4 assertions)` for the targeted PDF upload validation
  - `OK (12 tests, 44 assertions)` for the full final-file suite after isolating the MySQL testing schema per PHPUnit process
  - `OK (73 tests, 254 assertions)` for the full Content Management feature suite after isolating the MySQL testing schema per PHPUnit process

## 2026-07-09 (content management testing stabilization)

### Root cause confirmed
- `RefreshDatabase` was running against a single shared MySQL schema:
  - `qits_app_testing`
- When that shared schema was left partially migrated or when two PHPUnit invocations overlapped against it, Laravel's `migrate:fresh` / wipe cycle could observe mixed state:
  - existing tables with missing `migrations`
  - missing tables during drop
  - re-creation attempts against tables that another run had already recreated
- That is what surfaced MySQL errors:
  - `1050`
  - `1051`
  - `1146`

### Solution applied
- Kept MySQL and `RefreshDatabase`.
- Added isolated schema provisioning in `tests/CreatesApplication.php`.
- Under `APP_ENV=testing`, each PHPUnit process now:
  - derives a schema from the `.env.testing` base name
  - recreates only that process-scoped schema before Laravel boots
  - points `DB_DATABASE` to that isolated schema for the full run

### Validation result
- `tests/Feature/ContentManagement/ContentArticleFinalFilePanelTest.php`
  - passed completely
- `tests/Feature/ContentManagement`
  - passed completely

## 2026-07-09 (content management manual delivery and publication)

### Context
- Scope limited to manual delivery marking/correction and manual publication registration.
- Delivery and publication remain intentionally separated and do not infer each other.

### Changes made
- Added service:
  - `app/Services/ContentManagement/ContentArticleReleaseService.php`
- Added Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleDeliveryPublicationPanel.php`
- Added Livewire view:
  - `resources/views/livewire/admin/content-management/content-article-delivery-publication-panel.blade.php`
- Extended operational detail view:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
- Extended listing validation coverage:
  - `tests/Feature/ContentManagement/ContentArticleIndexTest.php`
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentArticleDeliveryPublicationPanelTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Implemented behavior
- Delivery:
  - requires at least one final file
  - stores `delivered_at` and `delivered_by`
  - does not infer publication
  - can be explicitly reverted to `null`
- Publication:
  - requires valid `published_url`
  - stores `published_at`, `published_by`, `published_url`
  - sets `main_status = published`
  - keeps `operational_stage = completed`
  - does not alter delivery fields
  - allows explicit `published_url` update after publication
- UI:
  - added a dedicated panel in the article detail
  - listing continues to reflect delivery via `delivered_at` and publication via `published_at`, independently of each other
- Security:
  - tenant access is revalidated server-side on every delivery/publication action through `ContentAccessService`

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleDeliveryPublicationPanelTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (9 tests, 33 assertions)` for the delivery/publication suite
  - `OK (83 tests, 292 assertions)` for the full Content Management feature suite

## 2026-07-09 (content management navigation integration)

### Context
- Scope limited to exposing the implemented Content Management module from the existing application navigation.
- No module routes or functional behavior were changed.

### Changes made
- Updated the real Jetstream navigation view:
  - `resources/views/navigation-menu.blade.php`
- Added desktop navigation link:
  - `Gestión de Contenidos`
  - `route('admin.content-management.index')`
- Added responsive/mobile navigation link using the same Jetstream responsive-nav pattern.
- Fixed the existing responsive Dashboard/SEO block so Dashboard and SEO render as separate responsive links.
- Added automated tests:
  - `tests/Feature/ContentManagement/ContentManagementNavigationTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Active state rule
- Content Management navigation is active for:
  - `admin.content-management.index`
  - any `admin.content-management*` route

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentManagementNavigationTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (3 tests, 15 assertions)` for the navigation suite
  - `OK (86 tests, 307 assertions)` for the full Content Management feature suite

## 2026-07-09 (content management objective template availability handling)

### Context
- Production reported a 500 while generating Prompt 1:
  - `Active master template version for objective step is not available.`
- Scope limited to handling missing active master template availability for the `objective` step.
- No prompt text, routes, module states or fallback prompt behavior were changed.

### Root cause
- Runtime Prompt 1 generation requires an active `content_master_template_versions` row whose master template has:
  - `content_master_templates.key = objective`
  - `content_master_templates.is_active = true`
  - `content_master_template_versions.is_active = true`
- The error means that this active pair was not available at runtime.

### Changes made
- Added exception:
  - `app/Exceptions/ContentManagement/MissingActiveTemplateVersionException.php`
- Updated service:
  - `app/Services/ContentManagement/ContentObjectivePromptService.php`
  - now throws a domain-specific exception with template availability context
- Updated Livewire component:
  - `app/Http/Livewire/Admin/ContentManagement/ContentArticleObjectiveDetail.php`
  - catches the missing-template exception and prevents a user-facing 500
  - logs technical details under `[CONTENT][PROMPT][OBJECTIVE_TEMPLATE_MISSING]`
- Updated Livewire view:
  - `resources/views/livewire/admin/content-management/content-article-objective-detail.blade.php`
  - shows the controlled message:
    - `La plantilla necesaria para este paso no está configurada. Contacta al administrador.`
- Extended automated tests:
  - `tests/Feature/ContentManagement/ContentArticleObjectiveDetailTest.php`

### Seeder strategy confirmed
- `Database\Seeders\ContentMasterTemplatesSeeder` remains the safe registration path for:
  - `objective`
  - `drafting`
  - `video_instagram`
- The seeder remains idempotent:
  - it creates missing templates and initial active versions
  - it reuses version `1` only when the stored body matches the approved TXT exactly
  - it does not silently overwrite historical content
  - it fails explicitly if a conflicting historical version exists

### Test execution
- Executed:
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentArticleObjectiveDetailTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement/ContentMasterTemplatesSeederTest.php`
  - `php vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (9 tests, 34 assertions)` for the objective flow suite
  - `OK (3 tests, 24 assertions)` for the master template seeder suite
  - `OK (87 tests, 310 assertions)` for the full Content Management feature suite

## 2026-07-09 (content article initial pending status)

### Context
- Newly imported Content Management articles were appearing as `processing` before any operational action had been executed.
- Scope limited to the initial article main status and safe correction of unstarted records.

### Changes made
- Added `pending` as a valid `content_articles.main_status` value in the Eloquent model.
- Added migration:
  - `database/migrations/2026_07_09_160000_add_pending_main_status_to_content_articles.php`
- Added data correction service:
  - `app/Services/ContentManagement/ContentArticleInitialStatusBackfillService.php`
- Updated XLSX import persistence:
  - new imported articles now use `main_status = pending`
  - `operational_stage = pending` remains unchanged
- Kept Prompt 1 first-generation behavior:
  - first real `objective` generation moves the article to `main_status = processing`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Data correction strategy
- The migration updates only records matching all conditions:
  - `main_status = processing`
  - `operational_stage = pending`
  - no rows in `content_article_generations`
- Records with generations or a started operational stage are not modified.

### Automated tests added or updated
- Imported XLSX articles start as `pending`.
- First Prompt 1 generation changes `pending` to `processing`.
- Started `processing` articles with prior objective generation keep `processing`.
- Backfill only affects unstarted records.

### Test execution
- Executed with PHP 8.1:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement/ContentXlsxImportServiceTest.php tests/Feature/ContentManagement/ContentArticleObjectiveDetailTest.php tests/Feature/ContentManagement/ContentArticlePendingStatusBackfillTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (9 tests, 40 assertions)` for the focused status suites
  - `OK (89 tests, 319 assertions)` for the full Content Management feature suite
- Environment note:
  - the default `php` on PATH is PHP 7.4.19 and fails Composer platform checks
  - PHP 8.1 run emits local startup warnings for missing `oci8_12c` and `pdo_firebird`, but tests pass

## 2026-07-09 (content management visible Spanish labels)

### Context
- Content Management screens were exposing internal enum/code values such as `processing`, `pending`, `drafting`, `objective`, `video_instagram`, `ready_by` and `ready_at`.
- Scope limited to visible UI text and user-facing messages.
- Internal database values and business logic were not changed.

### Changes made
- Added centralized label helper:
  - `app/Support/ContentManagementLabels.php`
- Updated visible labels in:
  - main article listing
  - article operational detail
  - Prompt 1 panel
  - Prompt 2 panel
  - Prompt 3 panel
  - final file panel
- Updated user-facing flow messages in:
  - `ContentArticleObjectiveDetail`
  - `ContentArticleDraftingPanel`
  - `ContentArticleVideoInstagramPanel`
  - drafting/video/final-file services
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Label strategy
- `main_status`, `operational_stage`, `step_type` and `step_status` continue to use existing internal enum values.
- Blade and user-facing messages render Spanish labels through `ContentManagementLabels`.

### Automated tests added or updated
- Listing shows `Pendiente` and `En proceso`.
- Listing does not show visible internal codes such as `PROCESSING`, `pending` or `drafting`.
- Detail view shows Spanish labels for article state and step states.

### Test execution
- Executed with PHP 8.1:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (91 tests, 337 assertions)` for the full Content Management feature suite
- Environment note:
  - PHP 8.1 run still emits local startup warnings for missing `oci8_12c` and `pdo_firebird`, but tests pass

## 2026-07-09 (content management operational detail card layout)

### Context
- The operational article detail separated each step into multiple visual blocks for generation, results, history and state.
- Scope limited to reorganizing the visible Blade composition.
- Business logic, services, transitions, database schema and Livewire component boundaries were not changed.
- XLSX import indicators, XLSX alert characters, post-save reactivity and action-message placement remain intentionally unchanged for later tasks.

### Changes made
- Reorganized Step 1 into one visual card:
  - `Paso 1 · Definir objetivo y público`
  - status, explanation, Prompt 1 generation, copy action, prompt preview, refined fields, history and ready action are grouped together
- Reorganized Step 2 into one visual card:
  - `Paso 2 · Redactar artículo`
  - status, blocking message, Prompt 2 generation, copy action, prompt preview, history and ready action are grouped together
- Reorganized Step 3 into one visual card:
  - `Paso 3 · Crear contenido para video e Instagram`
  - status, blocking message, Word/PDF instruction, Prompt 3 generation, copy action, prompt preview, history and ready action are grouped together
- Kept separate cards/components for:
  - final file
  - delivery
  - publication
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Automated tests updated
- Detail view asserts Spanish titles for Step 1, Step 2 and Step 3.
- Detail view asserts blocked steps are visibly identified as `Bloqueado`.
- Existing Content Management actions and navigation remain covered by the full feature suite.

### Test execution
- Executed with PHP 8.1:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (91 tests, 345 assertions)` for the full Content Management feature suite
- Environment note:
  - PHP 8.1 run still emits local startup warnings for missing `oci8_12c` and `pdo_firebird`, but tests pass

## 2026-07-09 (content import UI loading and UTF-8 text)

### Context
- Production import UI showed button spinners/loading text at rest and overlapping normal button labels.
- The XLSX import UI and validation summary also showed mojibake in Spanish text, such as malformed `importación` and `validación`.
- Scope limited to Content Management XLSX import UI text/loading behavior.
- Prompt 2 reactivity, refined-field reactivity and message placement inside cards were intentionally not changed.

### Root cause
- Loading indicators used `wire:loading` on elements that also had Tailwind display classes like `inline-flex`; in Livewire 2 this can leave the loading element visible at rest when CSS display rules conflict.
- Several PHP and Blade source strings already contained incorrectly encoded text, so the UI rendered the corrupted bytes directly.

### Changes made
- Updated import Livewire component text:
  - `app/Http/Livewire/Admin/ContentManagement/ContentImportManager.php`
- Updated import Blade:
  - `resources/views/livewire/admin/content-management/content-import-manager.blade.php`
- Updated import validation summary partial:
  - `resources/views/livewire/admin/content-management/partials/import-validation-summary.blade.php`
- Updated import tests:
  - `tests/Feature/ContentManagement/ContentImportManagerMountTest.php`
- Updated technical documentation:
  - `ARCHITECTURE.md`
  - `LOG.md`

### Loading strategy
- Validation button:
  - normal text uses `wire:loading.remove wire:target="validateImport"`
  - loading text/spinner uses `wire:loading.flex wire:target="validateImport" style="display: none;"`
- Confirmation button:
  - normal text uses `wire:loading.remove wire:target="confirmImport"`
  - loading text/spinner uses `wire:loading.flex wire:target="confirmImport" style="display: none;"`
- Removed the combined `validateImport,xlsxFile` target from the validation button so button loading follows only its own action.

### Test execution
- Executed with PHP 8.1:
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement/ContentImportManagerMountTest.php tests/Feature/ContentManagement/ContentXlsxImportServiceTest.php`
  - `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe vendor/bin/phpunit tests/Feature/ContentManagement`
- Result:
  - `OK (8 tests, 35 assertions)` for the import-focused suites
  - `OK (93 tests, 363 assertions)` for the full Content Management feature suite
