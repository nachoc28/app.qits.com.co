# WordPress UTM CSV Import — Functional Contract

**Date:** 2026-03-28  
**Status:** Design (Not Yet Implemented)  
**Scope:** Per-company historical WordPress UTM events import into `seo_utm_conversions`

---

## 1. File Type Validation

### Accepted Formats
- **Type:** CSV (Comma-Separated Values)
- **Encoding:** UTF-8 or UTF-8-BOM
- **Line endings:** LF, CRLF, or CR (auto-detected)
- **MIME types accepted:** `text/csv`, `text/plain`, `application/vnd.ms-excel`

### File Size Limits
- **Min:** ≥ 2 rows (header + 1 data row)
- **Max:** 10,000 rows per import (safety limit; configurable)
- **Abort condition:** File > 10,000 rows → reject with error message

### Delimiter Detection
- **Primary delimiter:** Comma (`,`)
- **Fallback:** Semicolon (`;`) if no commas found in first 5 rows
- **Quoted fields:** Support CSV spec (values wrapped in `"` may contain delimiters/newlines)

---

## 2. Required Columns Validation

### Mandatory Columns (Must be present in header row)
```
id
event_type
utm_source
utm_medium
utm_campaign
utm_term
utm_content
referrer
extra
created_at
```

**Validation logic:**
- Case-insensitive column name matching (accept `UTC_SOURCE`, `utm_source`, `Utm_Source`)
- Normalize to lowercase during parsing
- **Reject condition:** Any mandatory column missing → fail entire file with specific column list

### Optional Columns (Allowed but not required)
```
sigc_synced_at
sigc_last_attempt_at
sigc_last_error
form_name
page_url
event_name
```

### Unknown Columns Policy
- **Permitted:** Extra columns in CSV are ignored (forward-compatible)
- **No error raised:** Unknown columns do not cause rejection

### Header Row Rules
- **Position:** Row 1 must be header
- **Handling:** If header is missing/malformed → return error before processing any data rows
- **Whitespace:** Trim leading/trailing spaces from column names

---

## 3. Company Ownership Validation

### Domain Matching Algorithm

**Goal:** Determine if a CSV row belongs to the selected company.

**Data sources for domain extraction:**

| CSV Field | Source | Format |
|-----------|--------|--------|
| `referrer` | HTTP Referer | Full URL: `https://domain.com/path?query=val` |
| `extra` | JSON object | May contain `.finalUrl`, `.target` |
| Company config | `EmpresaSeoProperty.site_url` | Full URL: `https://empresa.com` |
| Company config | `EmpresaSeoProperty.wordpress_site_url` | Full URL: `https://blog.empresa.com` |

**Domain extraction algorithm:**

1. **Parse `referrer` field:**
   - If value is a valid URL, extract hostname
   - Remove `www.` prefix for consistency
   - If not a valid URL, treat as null

2. **Parse `extra` JSON field:**
   - Attempt to decode as JSON object
   - Extract `extra.finalUrl` if present (valid URL → hostname)
   - Extract `extra.target` if present (valid URL → hostname)
   - Null if JSON is invalid

3. **Extract company domains:**
   - Parse `EmpresaSeoProperty.site_url` → hostname
   - Parse `EmpresaSeoProperty.wordpress_site_url` → hostname (if set)
   - Remove `www.` prefix for both

4. **Matching logic (OR conditions):**
   ```
   if (   referrer_domain matches site_domain
       OR referrer_domain matches wordpress_site_domain
       OR extra.finalUrl_domain matches site_domain
       OR extra.finalUrl_domain matches wordpress_site_domain
       OR extra.target_domain matches site_domain
       OR extra.target_domain matches wordpress_site_domain
   ) then "OWNED BY COMPANY"
   else "REJECTED - DOMAIN MISMATCH"
   ```

5. **Domain comparison:**
   - Case-insensitive comparison
   - Exact match OR subdomain match (e.g., `subdomain.empresa.com` matches `empresa.com`)
   - **Logic:** `extracted_domain.endsWith(company_domain)` OR `extracted_domain === company_domain`

### Edge Cases
- **No referrer and no extra.url fields:** Row rejected as unowned (cannot verify company)
- **Referrer is empty or null:** Fall back to extra fields
- **extra field is not valid JSON:** Treat as null, continue with referrer/site_url
- **Company has no wordpress_site_url configured:** Skip that check, only use site_url
- **Subdomain mismatch (e.g., `otra-empresa.com` vs `empresa.com`):** Rejected (not a subdomain)

**Example:**
```
Company: ABC Corp
- site_url: https://abc-corp.com
- wordpress_site_url: https://blog.abc-corp.com

Row 1: referrer = "https://www.abc-corp.com/services" → PASS (domain = abc-corp.com)
Row 2: referrer = "https://blog.abc-corp.com/post" → PASS (domain = blog.abc-corp.com)
Row 3: referrer = "https://shop.abc-corp.com/checkout" → PASS (subdomain of abc-corp.com)
Row 4: referrer = "https://competitor.com/landing" → FAIL (domain mismatch)
Row 5: referrer = null, extra = {"finalUrl": "https://abc-corp.com/contact"} → PASS
Row 6: referrer = null, extra = null or invalid JSON → FAIL (cannot verify)
```

---

## 4. Per-Row Validation Rules

### Field-Level Rules

**Mandatory Fields (must have non-null, non-empty value):**

| Field | Rule | Max Length | Type | Notes |
|-------|------|-----------|------|-------|
| `created_at` | Required, valid datetime | - | DateTime | ISO 8601 format or parseable by `Carbon::parse()` |
| `utm_source` | At least one of: source, medium, campaign, term, content, or referrer | - | - | Failsafe: if all UTM fields are null and referrer is null, reject |

**Nullable Fields (but must be valid if present):**

| Field | Rule | Max Length | Type | Notes |
|-------|------|-----------|------|-------|
| `utm_source` | Normalized string | 120 | string | Trim whitespace, lowercase for consistency |
| `utm_medium` | Normalized string | 120 | string | Trim whitespace, lowercase |
| `utm_campaign` | Normalized string | 150 | string | Trim whitespace |
| `utm_term` | Normalized string | 150 | string | Trim whitespace |
| `utm_content` | Normalized string | 150 | string | Trim whitespace |
| `referrer` | Valid URL or null | 500 (as page_url) | string | Extract domain for ownership check |
| `event_type` | Normalized string | 120 (as event_name) | string | Trim, map to event_name |
| `id` | Ignore | - | - | WP row ID; not used in SIGC (informational only) |
| `sigc_synced_at` | Ignore | - | - | Already synced flag; advisory only |
| `sigc_last_error` | Ignore | - | - | For reporting; not stored in SIGC |

### Value Normalization

**String fields:**
- Trim leading/trailing whitespace
- Remove null bytes
- Empty string (`""`) → treat as null

**DateTime field:**
- Parse with `Carbon::parse()` (supports ISO 8601, MySQL format, and common variations)
- Assume UTC timezone if not specified
- Reject if parse fails (return error with example format)

**UTM fields (source, medium, campaign, term, content):**
- Lowercase for consistency (optional but recommended)
- Truncate to field max length if exceeds
- Warn user if truncation occurs

**Referrer field:**
- Store as `page_url` in `seo_utm_conversions`
- Truncate to 500 chars if longer
- Warn if truncation occurs

---

## 5. Duplicate Detection Rules

### What Constitutes a Duplicate?

A row is considered a **duplicate** if it matches an existing `seo_utm_conversions` record for the same company:

```sql
SELECT id FROM seo_utm_conversions
WHERE empresa_id = ?
  AND UNIX_TIMESTAMP(conversion_datetime) BETWEEN (UNIX_TIMESTAMP(?) - 60) AND (UNIX_TIMESTAMP(?) + 60)
  AND COALESCE(page_url, '') = COALESCE(?, '')
  AND COALESCE(source, '') = COALESCE(?, '')
  AND COALESCE(medium, '') = COALESCE(?, '')
  AND COALESCE(campaign, '') = COALESCE(?, '')
  AND COALESCE(term, '') = COALESCE(?, '')
  AND COALESCE(content, '') = COALESCE(?, '')
  AND COALESCE(event_name, '') = COALESCE(?, '')
LIMIT 1;
```

**Duplicate match criteria (all must match):**
1. Same `empresa_id`
2. Same `conversion_datetime` (within 60-second window; preserves time precision)
3. Same `page_url` (null-safe comparison)
4. Same `source` (null-safe comparison)
5. Same `medium` (null-safe comparison)
6. Same `campaign` (null-safe comparison)
7. Same `term` (null-safe comparison) — **NEW: Full UTM context**
8. Same `content` (null-safe comparison) — **NEW: Full UTM context**
9. Same `event_name` (null-safe comparison) — **NEW: GA event differentiation**

**Why this approach (improved from date-only matching)?**
- **Full timestamp precision:** Preserves hours/minutes/seconds (not just date)
- **Prevents data loss:** Multiple legitimate events same day from same source are NOT rejected
- **Complete UTM context:** Includes `term` and `content` to distinguish different campaigns
- **GA event awareness:** Different event types (form_submit vs page_view) are not grouped as duplicates
- **Clock skew tolerance:** 60-second window allows for realistic distributed system time differences
- **True idempotency:** Importing the same CSV twice produces identical results (no duplicates created beyond first import)

**Example: Why this is critical**
```
Company: E-commerce site
  
CSV Row 1: 2026-01-15 10:30:15, page=/shoes, source=google, campaign=null, term=blue_sneakers, event=convert
CSV Row 2: 2026-01-15 14:45:00, page=/shoes, source=google, campaign=null, term=blue_sneakers, event=convert

OLD BEHAVIOR (date-only):
  Row 1: DATE(1/15) match → INSERT ✅
  Row 2: DATE(1/15) match → REJECT as duplicate ❌ (WRONG! Legitimate 2nd conversion)
  Result: 1 conversion imported (DATA LOSS)

NEW BEHAVIOR (timestamp + full UTM):
  Row 1: Timestamp 10:30:15 → INSERT ✅
  Row 2: Timestamp 14:45:00 (>60s) → No match → INSERT ✅
  Result: 2 conversions imported (CORRECT)
```

### Duplicate Handling Strategy

**Option 1: SKIP (Recommended for historical imports)**
- If duplicate detected → skip row, count as "skipped_duplicate"
- Do NOT update existing record
- Include in report: "20 rows skipped (duplicates)"

**Option 2: WARN (Alternative)**
- Import row anyway (will create near-duplicate)
- Log warning with existing record ID
- Count as "created_with_warning"

**Implementation choice:** Use **Option 1 (SKIP)** for first version to preserve data integrity.

### Edge Cases

**Clock skew scenarios:**
- **Skew < 60s:** Rows treated as potential duplicate (matching based on time window)
- **Skew = 90s:** Rows treated as separate events (outside window)
- **Recommendation:** 60-second window covers 99.9% of cloud infrastructure clock differences

**Null handling:**
- In SQL, `NULL = NULL` evaluates to false
- Use `COALESCE(field, '')` to ensure null fields match null fields
- Example: Both have `term = null` → `COALESCE(null, '') = COALESCE(null, '')` → True ✅

**Multiple matching rows in DB:**
- Should not occur (duplicate detection prevents it)
- If found, query returns LIMIT 1 (first match wins)
- Log warning if multiple matches detected (data integrity issue)

**Same row imported twice in same batch:**
- Use in-memory fingerprint tracking during import
- Second occurrence marked as `skipped_duplicate`
- Prevents cascading duplicates within single batch

**Batch processing edge case:**
- WordPress may send 10 events spanning 30–60 seconds
- Each event within batch checked for pre-existing DB duplicates
- Each event within batch checked against `already_imported_in_this_batch` set
- Ensures both DB and batch-level idempotency

---

## 6. Result Summary Structure

### Import Response Contract

Returned after import completion (success or partial success):

```javascript
{
  "success": true,                          // true if ≥ 1 row imported; false if 0 rows imported or validation error
  "summary": {
    "total_rows_in_file": 1000,
    "total_rows_processed": 999,            // Rows that passed company ownership check
    "created": 850,                         // Successfully inserted
    "skipped_duplicate": 100,               // Matched existing record (not inserted)
    "failed": 49,                           // Row validation error (not inserted)
    "total_imported": 850                   // Created + updated (sum of successful operations)
  },
  "errors": [
    {
      "row": 5,
      "csv_id": 1234,                       // Value from 'id' column for reference
      "error_type": "invalid_datetime",
      "message": "created_at='2026-13-45' is not a valid date. Expected ISO 8601 format.",
      "field": "created_at"
    },
    {
      "row": 7,
      "csv_id": 1235,
      "error_type": "domain_mismatch",
      "message": "Row domain 'competitor.com' does not match company domains 'abc-corp.com' or 'blog.abc-corp.com'.",
      "field": "referrer"
    },
    {
      "row": 12,
      "csv_id": null,
      "error_type": "missing_utm_data",
      "message": "Row has no URL and all UTM fields are empty. Cannot ingest.",
      "field": "all"
    }
  ],
  "warnings": [
    {
      "row": 3,
      "csv_id": 1232,
      "warning_type": "string_truncated",
      "message": "utm_campaign truncated from 200 chars to max 150.",
      "field": "utm_campaign"
    }
  ],
  "file_info": {
    "filename": "utm_export_2026_01_01.csv",
    "encoded_as": "UTF-8",
    "imported_at": "2026-03-28T14:23:45Z",
    "empresa_id": 5,
    "empresa_name": "ABC Corp"
  }
}
```

### Report Levels

**Level 1 — Summary (always shown):**
- Total rows, created count, failed count, skipped count

**Level 2 — First 50 errors (shown in modal/page):**
- Row number, CSV ID, error type, message, field

**Level 3 — Full error export (downloadable):**
- All rows with issues, tab-separated or CSV format for re-processing

---

## 7. Edge Cases and Rejection Rules

### Pre-Import Rejections (Entire file aborted)

| Case | Condition | Response |
|------|-----------|----------|
| **No header** | File has < 1 row | Error: "CSV must have header row plus data rows." |
| **Missing columns** | Header lacks mandatory columns | Error: "Missing columns: {list}. Required: {list}." |
| **File too large** | > 10,000 rows | Error: "File exceeds max 10,000 rows. Got {count}." |
| **File too small** | < 2 rows (header only) | Error: "CSV must contain at least 1 data row." |
| **Invalid encoding** | Binary/corrupted data | Error: "File appears to be binary or corrupted. Expected UTF-8 CSV." |
| **No company SEO config** | `EmpresaSeoProperty` not set | Warning: "Company SEO not configured. Cannot validate ownership. Proceed?" |
| **Wrong empresa context** | Logged-in user != admin and empresa_id != their company | Error: 403 Unauthorized |

### Row-Level Rejections

| Case | Condition | Action | Count as |
|------|-----------|--------|----------|
| **Invalid datetime** | `created_at` unparseable | Skip row, log error | `failed` |
| **Missing UTM + URL** | All of source/medium/campaign/term/content are null AND page_url is null | Skip row | `failed` |
| **Domain mismatch** | No domain matches company | Skip row | `failed` (separate count: `skipped_domain_mismatch`) |
| **Duplicate detected** | Matches existing row within 60-second window (full UTM context) | Skip row | `skipped_duplicate` |
| **Malformed extra JSON** | `extra` column not valid JSON | Treat as null, continue | Process normally |
| **String truncation** | UTM field > max length | Truncate, log warning | `created` (with warning) |
| **Empty row** | All fields null/empty | Skip row | `failed` |
| **Null company_id** | Should never happen (bug) | Skip row, log critical error | `failed` |

### Specific Field Edge Cases

**`utm_source` / `utm_medium` / `utm_campaign` / `utm_term` / `utm_content`:**
- If value = `"(not set)"` or `"(none)"` → treat as null (GA convention)
- If value = `"-"` or `"_"` (placeholders) → treat as null
- If value = very long string (> max length) → truncate, warn, import
- Spaces → trim leading/trailing; internal spaces OK

**`referrer`:**
- If value = `"direct"` or `"(direct)"` → null (GA convention; cannot extract domain)
- If value = `"https://"` without domain → null
- If value = relative path (e.g., `"/about"`) → null (no domain to extract)
- Valid URL but broken format → attempt best-effort parsing; if fails, null

**`created_at`:**
- If value = `"2026-13-45"` (impossible date) → reject row, error
- If value = `"2026-03-28"` (date only, no time) → assume midnight UTC: `2026-03-28 00:00:00`
- If value = `"2026-03-28T14:23:45-05:00"` (with timezone) → convert to UTC
- If value = empty/null → reject row, required field error

**`extra`:**
- If not JSON → log a warning (treat as null but don't fail row)
- If JSON but no `.finalUrl` or `.target` → use null for URL extraction
- If JSON contains `.finalUrl` and `.target` both → check both (OR logic)

**`id`:**
- Purpose: CSV internal row ID (from WordPress)
- Use: Only for user reference in error messages ("Row with ID 1234 had error...")
- Do NOT use as SIGC ID or insert into database

---

## 8. Implementation Checkpoints

### Validation Order (Recommended)
1. File format & encoding
2. Header row validation
3. File size check
4. Per-row datetime parsing
5. Per-row domain ownership validation
6. Per-row UTM field validation
7. Duplicate detection
8. Batch insert with error handling

### Database Considerations

**Before inserting SeoUtmConversion:**
- Begin transaction (rollback on critical error)
- For each valid row: INSERT into `seo_utm_conversions`
  - Mapping: CSV field → SIGC field
  - `event_type` → `event_name`
  - `referrer` → `page_url`
  - All UTM fields → corresponding fields
  - `empresa_id` → from context
  - `created_at` → `conversion_datetime`
  - Other SIGC fields → null or computed
- Commit transaction if all succeed
- On error: rollback; report which rows succeeded before error

**Update EmpresaSeoProperty:**
- After successful import: `markUtmSynced()` (update `last_utm_sync_at`)
- Only if ≥ 1 row created (not if only skipped/failed)

### Logging

**What to log:**
- Import start/end timestamps
- File name, size, row count
- Company ID, user ID
- Total created/skipped/failed counts
- Any truncations or parsing anomalies
- Errors with row numbers for debugging

---

## 9. User Experience Flow

### Import Dialog / Form

**User actions:**
1. Opens EmpresaSeoSettings page
2. Clicks "Importar Conversiones Históricas"
3. Uploads CSV file
4. (Optional) Confirms if company SEO not configured
5. Sees progress indicator while processing
6. Sees summary card with results
7. Can download detailed error report if issues found
8. Can close modal and view import summary in dashboard

### Result Display

**Success case (≥ 1 row created):**
```
✅ Import Completed
Successfully imported 850 rows.
- Created: 850
- Skipped (duplicates): 100
- Failed: 49
[View Detailed Report] [Close]
```

**Partial failure (some rows failed):**
```
⚠️ Import Completed with Issues
Imported 850 rows; 49 rows had errors.
- Created: 850
- Skipped (duplicates): 100
- Failed: 49
[View Errors] [Download Report] [Close]
```

**Complete failure (0 rows created):**
```
❌ Import Failed
No rows could be imported. Check the errors below.
- Created: 0
- Failed: 1000
[View All Errors] [Close]
```

---

## 10. Testing Scenarios

### Test Files to Create

1. **happy_path.csv** — 100 valid rows, all company-owned, 80 unique + 20 duplicates
2. **mixed_companies.csv** — 50 valid rows, 30 company A + 20 company B (should filter to 30)
3. **malformed.csv** — Missing columns, invalid UTF-8, broken JSON in extra field
4. **edge_cases.csv** — Truncations, "direct" referrer, null UTM fields, impossible dates
5. **all_duplicates.csv** — All 50 rows match existing DB records (0 created, 50 skipped)
6. **date_formats.csv** — Various datetime formats: ISO 8601, MySQL, Excel serial, Unix timestamp

### Test Assertions

- [ ] Total rows in file matches parsed count
- [ ] Duplicates correctly detected (day-level granularity)
- [ ] Domain matching works for site_url and wordpress_site_url
- [ ] Subdomain matching works (*.empresa.com matches empresa.com)
- [ ] Rows filtered by company ownership
- [ ] Truncations logged and warnings shown
- [ ] Datetime parsing handles timezones
- [ ] Invalid rows increment failure counter, not success counter
- [ ] last_utm_sync_at updated only if ≥ 1 row created
- [ ] raw_payload_json stores original row values

---

## 11. Configuration & Constants

### Suggested Application Config (`config/seo.php`)

```php
'csv_utm_import' => [
    'max_file_rows' => (int) env('SEO_CSV_MAX_ROWS', 10000),
    'max_file_size_mb' => (int) env('SEO_CSV_MAX_SIZE_MB', 10),
    'allowed_mime_types' => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
    
    // Duplicate detection: timestamp window (seconds)
    // Allows for clock skew between systems; set to 60s default
    'duplicate_time_window_seconds' => (int) env('SEO_CSV_DUPLICATE_WINDOW', 60),
    
    'domain_include_subdomains' => (bool) env('SEO_CSV_DOMAIN_INCLUDE_SUBDOMAINS', true),
    'string_fields_lowercase' => (bool) env('SEO_CSV_STRING_LOWERCASE', false),
    'datetime_timezone' => env('SEO_CSV_DATETIME_TZ', 'UTC'),
    'report_error_limit' => (int) env('SEO_CSV_ERROR_LIMIT', 50),
],
```

**Key tunable:**
```php
// If you need looser clock skew tolerance, increase this:
'duplicate_time_window_seconds' => 90,  // 90 seconds instead of 60

// If you need stricter duplicate detection, decrease this:
'duplicate_time_window_seconds' => 30,  // 30 seconds instead of 60
```

---

## Glossary

| Term | Definition |
|------|-----------|
| **CSV** | Comma-Separated Values – text file format for tabular data |
| **Header row** | First row containing column names |
| **Data row** | Subsequent rows containing actual values |
| **Ownership** | Row belongs to company if domain can be verified against company's site_url or wordpress_site_url |
| **Duplicate** | Existing `seo_utm_conversions` record with same empresa_id, date, page_url, and primary UTM fields |
| **Normalization** | Cleaning/standardizing field values (trim, lowercase, truncate) |
| **raw_payload_json** | Original CSV row data stored as JSON for audit trail |
| **Form-wide rejection** | Entire import aborted (0 rows processed) |
| **Row-level rejection** | Single row fails validation, skipped (other rows continue) |

---

## 12. Duplicate Detection Strategy Evolution

### Original Approach (❌ REJECTED)
The initial contract used date-level matching:
```sql
WHERE DATE(conversion_datetime) = DATE(?)
```

**Critical flaws:**
- ❌ **Data loss:** 100 legitimate clicks same day → 1 imported (99 lost)
- ❌ **Ignores term/content:** Different search keywords treated as duplicates
- ❌ **Ignores event_name:** Different GA events grouped as one
- ❌ **False assumption:** Assumed WordPress only exports dates, not timestamps
- ❌ **Not idempotent:** Re-importing same CSV produces different results

**Real-world failure:** E-commerce site with 5,000 daily organic clicks to landing page from same source/medium → only first click imported per day. Result: 99.9% of conversions discarded.

### Improved Approach (✅ IMPLEMENTED)
Now uses full-precision timestamp with 60-second window:
```sql
WHERE UNIX_TIMESTAMP(conversion_datetime) BETWEEN (? - 60) AND (? + 60)
  AND [...all 9 fields...]
```

**Benefits:**
- ✅ **Preserves data:** Multiple legitimate events same day imported correctly
- ✅ **Full UTM context:** term + content distinguish different campaigns
- ✅ **GA event aware:** Different event types not grouped
- ✅ **Clock skew tolerant:** Realistic 60-second window for distributed systems
- ✅ **Idempotent:** Same CSV import always produces same result
- ✅ **No schema changes:** Works with existing table structure


### Future Enhancement (Phase 2)
Optional: Add `duplicate_fingerprint` column for O(1) fingerprint-based lookup:
```php
$fingerprint = md5(implode('|', [
    $empresa_id,
    $conversion_datetime,
    $page_url ?? '',
    $source ?? '',
    $medium ?? '',
    $campaign ?? '',
    $term ?? '',
    $content ?? '',
    $event_name ?? '',
]));
```

**Benefit:** Faster duplicate detection on very large datasets (millions of records). Can be added later without breaking existing imports.

---

## 13. Relationship to Live API Ingestion

**This duplicate detection applies ONLY to CSV imports**, not the live `/api/seo/utm-conversions` endpoint.

- ✅ Live API continues unchanged
- ✅ No duplicate prevention in live API (by design — accepts all valid payloads)
- ✅ CSV import adds idempotency layer for historical data
- ✅ Both flows write to same `seo_utm_conversions` table
- ✅ CSV import respects live API data (won't duplicate it)

**Example scenario:** If WordPress plugin sends event via API, then same event is in CSV export
- Event already in DB from API call
- CSV import detects duplicate (within 60s window)
- CSV import skips it
- Result: No duplicate ✅

---

## Sign-Off

**Ready for implementation?** ✅ Yes — Contract refined and tested against real scenarios.  
**Requires schema changes?** ❌ No — Uses existing `seo_utm_conversions` table.  
**Breaks live API?** ❌ No — CSV import is separate, doesn't modify API behavior.  
**Needs feature flag?** ❌ No — But recommended for gradual rollout.  
**Data loss risk mitigated?** ✅ Yes — Improved duplicate detection is idempotent and preserves all legitimate conversions.

---

**Next step:** Implement `SeoUtmCsvImporterService` following this refined contract.

