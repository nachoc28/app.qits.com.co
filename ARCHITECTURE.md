# QITS - Project Architecture

**Last Updated:** 2026-05-07  
**Version:** 1.0  
**Stack:** Laravel 8, Jetstream, Livewire, Tailwind CSS, MySQL  

---

## 0. System Snapshot (AI Context)

**Current State (as of 2026-03-28):**

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
- Real-time event ingestion and processing
- API-first architecture for third-party integrations
- Role-based access control via Jetstream

**Primary Users:** Marketing agencies, sales teams, system administrators  
**Deployment:** Laragon (development), Shared hosting (production)  

---

## 2. Technology Stack

### Backend
- **Framework:** Laravel 8.x
- **Authentication:** Jetstream + Sanctum
- **ORM:** Eloquent
- **Database:** MySQL 5.7+
- **PHP:** 7.4+ (production compatibility required)
- **Background Jobs:** Laravel Queue (configuration via `config/queue.php`)

### Frontend
- **UI Framework:** Livewire (live components)
- **CSS:** Tailwind CSS 2.x
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

### Migration Strategy
- Migrations use timestamp prefix: `YYYY_MM_DD_HHMMSS_description`
- Schema modifications are backward-compatible
- See `/database/migrations` for version history

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
- **Web:** Blade `@auth`, `@can` directives
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

### Production Deployment (Shared Hosting)
- **PHP Version:** 7.4+ required
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
**Last Review:** 2026-03-28
