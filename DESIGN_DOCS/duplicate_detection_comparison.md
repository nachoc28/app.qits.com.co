# Duplicate Detection: Strategy Comparison

**Refinement Date:** 2026-03-28  
**Status:** Analysis Complete — Solution 1 (60-second window) Recommended for Implementation

---

## Executive Summary

| Aspect | Old Approach | New Approach | Why This Matters |
|--------|------------|------------------|------------------|
| **Data Loss Risk** | 🔴 **CRITICAL** (99% of events) | ✅ **NONE** | Real companies import thousands of daily events |
| **Idempotent** | ❌ NO | ✅ YES | Re-importing same CSV must be safe |
| **Full UTM Context** | ❌ Missing term, content, event_name | ✅ All fields included | Distinguishes different campaigns |
| **Same-Day Handling** | ❌ 5K clicks = 1 stored | ✅ All 5K stored | Ecommerce sites have multiple clicks/day |
| **Clock Skew** | ✅ Infinite tolerance | ✅ 60-second tolerance | Works with real distributed systems |
| **Schema Changes** | ❌ NO | ❌ NO | No migrations needed |
| **Performance** | ✅ Very fast | ⚠️ Good (acceptable) | Sub-millisecond query for typical cases |

---

## The Critical Problem with Old Approach

### Real Scenario: E-Commerce Site

**Company:** Shoe Store (site_url = footwear.com)

**WordPress exports historical UTM (January 2026):**
```
Row 1:  2026-01-15 10:30:00, page=/catalog, source=google, medium=organic, term=blue_shoes, event=page_view
Row 2:  2026-01-15 11:15:30, page=/catalog, source=google, medium=organic, term=blue_shoes, event=page_view  
Row 3:  2026-01-15 13:45:00, page=/catalog, source=google, medium=organic, term=blue_shoes, event=page_view
Row 4:  2026-01-15 16:20:15, page=/catalog, source=google, medium=organic, term=blue_shoes, event=page_view
Row 5:  2026-01-15 18:00:00, page=/catalog, source=google, medium=organic, term=blue_shoes, event=page_view
... (5,000 total rows same day, same search term)
```

### Old Duplicate Detection (DATE-based)

```sql
WHERE empresa_id = 1
  AND DATE(conversion_datetime) = DATE('2026-01-15')
  AND page_url = '/catalog'
  AND source = 'google'
  AND medium = 'organic'
  AND campaign = null
```

**Results:**
- ✅ Row 1: First match → **INSERT**
- ❌ Row 2: DATE matches → **SKIP (duplicate)**
- ❌ Row 3: DATE matches → **SKIP (duplicate)**
- ❌ Row 4: DATE matches → **SKIP (duplicate)**
- ❌ Row 5: DATE matches → **SKIP (duplicate)**

**Final:** **1 event imported** (out of 5,000) = **99.98% DATA LOSS** 🔴

### New Duplicate Detection (Timestamp + Full Context)

```sql
WHERE empresa_id = 1
  AND UNIX_TIMESTAMP(conversion_datetime) BETWEEN (UNIX_TIMESTAMP(?) - 60) AND (UNIX_TIMESTAMP(?) + 60)
  AND page_url = '/catalog'
  AND source = 'google'
  AND medium = 'organic'
  AND campaign = null
  AND term = 'blue_shoes'
  AND content = null
  AND event_name = 'page_view'
```

**Results:**
- ✅ Row 1 @ 10:30:00: No match in DB → **INSERT**
- ✅ Row 2 @ 11:15:30: 45 minutes later (outside 60s) → No match → **INSERT**
- ✅ Row 3 @ 13:45:00: 130 minutes later (outside 60s) → No match → **INSERT**
- ✅ Row 4 @ 16:20:15: 155 minutes later (outside 60s) → No match → **INSERT**
- ✅ Row 5 @ 18:00:00: 100 minutes later (outside 60s) → No match → **INSERT**

**Final:** **5,000 events imported** (all of them) = **100% PRESERVED** ✅

---

## Why 60-Second Window?

### Real-World Clock Scenarios

| Scenario | Clock Difference | Within 60s? | Handling |
|----------|------------------|-----------|----------|
| **Same server** | ±50ms | ✅ YES | Identical rows → duplicate |
| **Nearby servers** | ±5 seconds | ✅ YES | Minor clock drift → duplicate |
| **Different datacenters** | ±15 seconds | ✅ YES | Typical NTP sync → duplicate |
| **NTP sync pending** | ±30 seconds | ✅ YES | Temporary skew → duplicate |
| **Large clock skew** | ±45 seconds | ✅ YES | Cloud failover scenario → duplicate |
| **Extreme (rare)** | ±120 seconds | ❌ NO | Very rare; can get near-dups |

**Why not 5 minutes?** Too loose; true duplicates become accepted.  
**Why not 5 seconds?** Too strict; real clock skew causes false negatives.  
**60 seconds:** Sweet spot for enterprise systems.

### Configurable for Special Cases

If your infrastructure has known larger clock skew:
```php
# config/seo.php
'csv_utm_import' => [
    'duplicate_time_window_seconds' => 90,  // Increase if needed
],
```

---

## Full Field Comparison

### Old (❌ Date-based)

```javascript
{
  empresa_id:              5,            // ✅ checked
  DATE(conversion_datetime): 2026-01-15, // ❌ TIME LOST!
  
  // Full UTM context:
  page_url:       '/product',     // ✅ checked
  source:         'google',       // ✅ checked
  medium:         'organic',      // ✅ checked
  campaign:       null,           // ✅ checked
  term:           'shoes',        // ❌ NOT checked (different terms = same dup!)
  content:        'variant-a',    // ❌ NOT checked (A/B tests treated as dup!)
  event_name:     'add_to_cart',  // ❌ NOT checked (different events = same dup!)
}
```

### New (✅ Timestamp + Complete Context)

```javascript
{
  empresa_id:              5,                      // ✅ checked
  UNIX_TIMESTAMP(±60s):    1673769000,             // ✅ FULL PRECISION!
  
  // Full UTM context:
  page_url:       '/product',           // ✅ checked
  source:         'google',             // ✅ checked
  medium:         'organic',            // ✅ checked
  campaign:       null,                 // ✅ checked
  term:           'shoes',              // ✅ NOW checked (different terms = different!)
  content:        'variant-a',          // ✅ NOW checked (A/B variants = different!)
  event_name:     'add_to_cart',        // ✅ NOW checked (different events = different!)
}
```

---

## Test Cases: Expected Behavior

### Test 1: Same Row, Different Times (Should Import Both)

```csv
created_at,page_url,source,medium,campaign,term,content,event_name,...
2026-01-15 10:30:00,/shoes,google,organic,,blue_shoes,,convert,...
2026-01-15 14:45:00,/shoes,google,organic,,blue_shoes,,convert,...
```

**Old behavior:** Both rows match → second skipped ❌  
**New behavior:** 244 mins apart → both imported ✅  
**Verdict:** Data preserved ✅

---

### Test 2: Same Row Within 60s (Should Skip Duplicate)

```csv
created_at,page_url,source,medium,campaign,term,...
2026-01-15 10:30:00,/shoes,google,organic,,blue_shoes,...  
2026-01-15 10:30:35,/shoes,google,organic,,blue_shoes,...
```

**Old behavior:** Both rows match → second skipped ✅  
**New behavior:** 35s apart (within 60s window) → second skipped ✅  
**Verdict:** True duplicate eliminated ✅

---

### Test 3: Different Terms (Should Import Both)

```csv
created_at,page_url,source,medium,campaign,term,...
2026-01-15 10:30:00,/shoes,google,organic,,blue_shoes,...  
2026-01-15 10:30:10,/shoes,google,organic,,red_shoes,...
```

**Old behavior:** Both rows match (`term` not checked) → second skipped ❌  
**New behavior:** Different `term` → both imported ✅  
**Verdict:** Campaign variations preserved ✅

---

### Test 4: Different Event Name (Should Import Both)

```csv
created_at,page_url,source,medium,campaign,event_name,...
2026-01-15 10:30:00,/shoes,google,organic,,page_view,...  
2026-01-15 10:30:05,/shoes,google,organic,,add_to_cart,...
```

**Old behavior:** Both rows match (`event_name` not checked) → second skipped ❌  
**New behavior:** Different `event_name` → both imported ✅  
**Verdict:** GA event context preserved ✅

---

### Test 5: Re-import Same CSV (Idempotency Check)

**First import:**
```
5000 rows → 4800 created, 200 skipped (DB duplicates)
```

**Second import (same CSV, 1 hour later):**
```
5000 rows → 0 created, 5000 skipped (all now in DB)
```

**Third import (same CSV, 1 day later):**
```
5000 rows → 0 created, 5000 skipped (all still in DB)
```

**Old behavior:** Would be unpredictable (time-dependent) ❌  
**New behavior:** Always consistent (fingerprint deterministic) ✅  
**Verdict:** True idempotency ✅

---

## Why Not Fingerprint in Phase 1?

### Fingerprint Approach (Good, but for Phase 2)

```php
$fingerprint = md5(implode('|', [
    $empresa_id,
    $conversion_datetime->toDateTimeString(),
    $page_url ?? '',
    $source ?? '',
    // ... all 8 fields
]));
```

**Advantages:**
- O(1) lookup (hash index vs range scan)
- Deterministic (exact match only)
- Fast for millions of records

**Why wait for Phase 2?**
- ❌ Requires schema change (ALTER TABLE)
- ⚠️ Backward compatibility considerations
- ✅ Phase 1 (time-window) works today
- ✅ Can migrate to fingerprint later

**Transition path:**
1. Phase 1: Deploy time-window duplicate detection ✅
2. Validate: Run for 2 weeks, confirm no issues
3. Phase 2: Add fingerprint column + migration
4. Phase 2: Switch to fingerprint-based lookup (faster)
5. Phase 2: Keep old query as fallback

---

## Implementation Summary

### No Schema Changes Required ✅
- Uses existing `seo_utm_conversions` table
- No new columns needed
- No index changes needed (uses existing `[empresa_id, conversion_datetime]` index)

### No API Changes Required ✅
- Live `/api/seo/utm-conversions` endpoint untouched
- CSV import is separate workflow
- Both use same table; CSV respects existing data

### Configuration Only
```php
'csv_utm_import' => [
    'duplicate_time_window_seconds' => 60,  // Tunable
],
```

### Performance
- **Query:** Sub-millisecond (uses indexed range on empresa_id + datetime)
- **Storage:** No overhead (same table)
- **Memory:** Minimal (in-batch fingerprints tracked in PHP array)

---

## Decision: Solution 1 (60-Second Window)

### ✅ Recommended Now
- **Why:** Fixes data loss immediately, no schema changes
- **Risk:** Minimal (time-window based on proven practices)
- **Timeline:** Can be deployed March 28, 2026
- **Validation:** 2-week pilot, then consider Phase 2

### 🚀 Phase 2: Fingerprint Enhancement
- **Timeline:** April 15, 2026 (after Phase 1 validation)
- **Benefit:** Faster lookups on historical data
- **Backward compatible:** Existing CSV imports continue working

---

## Summary of Changes from Original Contract

| Aspect | Original | Refined | Impact |
|--------|----------|---------|--------|
| **Duplicate detection** | DATE() + 4 fields | TIMESTAMP(±60s) + 9 fields | Prevents data loss |
| **term field** | Ignored | Checked | Distinguishes UTM campaigns |
| **content field** | Ignored | Checked | Distinguishes A/B tests |
| **event_name field** | Ignored | Checked | Distinguishes GA events |
| **Idempotency** | No | Yes | Safe re-imports |
| **Same-day events** | First only | All imported | Preserves multi-event days |
| **Configuration** | N/A | 60-second window | Tunable per environment |

