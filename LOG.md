# LOG

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
