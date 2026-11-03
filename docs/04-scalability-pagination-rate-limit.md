# 04 — Scalability · Pagination · Rate Limiting

## A. Scalability

### Principles
1. **Stateless API** — session server pe nahi; JWT + Redis. **Frontend alag static files** hain, CDN pe serve karo (backend ka load zero). Kitne bhi instance peeche laga sakte hain.
2. **Queue everything slow** — SMS, push, invoice PDF, CSV import, email. API turant respond kare.
3. **Cache read-heavy** — categories, brand, catalog list (Redis, tag-based invalidation jab stock/price badle).
4. **Files DB me nahi** — images/prescriptions Object Storage (S3-compatible) + CDN. Rx private bucket + signed URLs.
5. **Idempotency** — order create aur payment verify `Idempotency-Key` se; webhook retry safe.
6. **Correct stock under load** — `SELECT ... FOR UPDATE` on batches, transaction short rakho; oversell nahi.

### Stages
| Stage | Users | Setup |
|---|---|---|
| 1 Launch | < 10k | 1 VPS: Nginx + PHP-FPM + MySQL + Redis, queue worker (Supervisor) |
| 2 Growth | 10k–100k | App server x2-3 behind LB, managed MySQL + 1 read replica, managed Redis, S3 + CDN, queue workers Supervisor se (2-5 process) |
| 3 Scale | 100k+ | Autoscaling containers, Meilisearch/Elasticsearch search ke liye (`SearchService` ke peeche swap), replica reads (catalog/search), table partitioning (`order_status_history`, `notifications`, `audit_logs` by month), separate queue per type |

### Database
- Indexes: `medicines(name)`, FULLTEXT `(name, generic_name)`, `salts(name)`, `batches(medicine_id, exp_date)`, `batches(exp_date)`, `orders(user_id, created_at)`, `orders(status, created_at)`, `notifications(user_id, id)`.
- Money `DECIMAL(10,2)`; UTC timestamps; soft deletes on medicines.
- Slow query log on; `EXPLAIN` har list query ke liye.
- Archive: 2 saal purane delivered orders cold storage (invoices retain — legal).
- Backups: daily full + binlog PITR; restore drill har quarter.

### Search auto-suggest (fast)
- Stage 1: MySQL FULLTEXT + prefix `LIKE 'abc%'` on indexed column, `LIMIT 8`, Redis cache 60s per query.
- Stage 3: Meilisearch (typo-tolerance, salt synonyms).
- Client debounce 250 ms, min 2 chars, previous request cancel.

### Observability
Sentry (errors), Monolog structured logs, Prometheus/Grafana ya hosted APM, uptime ping, alert: queue lag > 60s, 5xx > 1%, DB replica lag.

### Deployment
Docker images, GitHub Actions CI → staging → manual approve → prod. Zero-downtime (rolling), `php bin/migrate.php` backward-compatible migrations only.

---

## B. Pagination standard

### Types
| Use | Type | Kyun |
|---|---|---|
| Admin tables (medicines, orders, customers) | **Offset** `page` + `per_page` | page numbers + "Showing X of Y" (original UI jaisa), jump to page |
| Mobile infinite scroll (catalog, orders, notifications) | **Cursor** `cursor` + `limit` | fast, duplicates nahi, bade offsets pe slow nahi |

### Rules
- Default `per_page=20`, **max 100** (server clamp). Cursor `limit` default 20, max 50.
- Stable sort + tie-breaker `id` (e.g. `ORDER BY exp_date ASC, id ASC`).
- Whitelisted sort fields only.
- `total` count sirf offset type me; bade tables pe `total` cache/approx.

### Request / Response
```
GET /api/v1/admin/medicines?page=2&per_page=20&sort=exp_date&dir=asc&q=para&category=Tablet
GET /api/v1/medicines?limit=20&cursor=eyJpZCI6MTIwfQ
```
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "pagination": {
      "type": "offset",
      "page": 2, "per_page": 20, "total": 143, "last_page": 8,
      "from": 21, "to": 40
    }
  }
}
```
```json
{ "meta": { "pagination": { "type": "cursor", "limit": 20, "next_cursor": "eyJpZCI6MTQwfQ", "has_more": true } } }
```
PHP: `Core\Paginator::offset()` / `::cursor()` — dono same meta shape return karte hain. Flutter: `Paginator<T>` helper (loadMore, refresh, hasMore, error state).

---

## C. Rate limiting

Redis-backed `Core\RateLimiter` (limits `config/rate_limits.php` me, `RateLimit` middleware route group pe lagta hai). Key = user id (login ho to) warna IP; OTP ke liye **phone/email + IP** dono.

| Endpoint group | Limit | Key | Note |
|---|---|---|---|
| `POST /auth/otp/request` | 1 / min · 5 / hour | identifier | + 10 / hour per IP |
| `POST /auth/otp/verify` | 5 attempts / OTP → 15 min lock | identifier | 20 / hour per IP |
| `GET /search/suggest` | 60 / min | user/IP | cached results |
| Public catalog GET | 120 / min | IP | |
| Authenticated API (default) | 120 / min | user | |
| `POST /prescriptions` | 10 / hour | user | file abuse rokne ke liye |
| `POST /orders` | 10 / min | user | + idempotency |
| `POST /payments/*` | 20 / min | user | |
| Admin API | 300 / min | user | |
| `POST /admin/import/csv` | 5 / hour | user | queued job |
| Webhooks | exempt | — | signature verify + IP allowlist |

### Response
```
HTTP/1.1 429 Too Many Requests
Retry-After: 42
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
{"success":false,"error":{"code":"RATE_LIMITED","message":"Bahut zyada requests. 42 sec baad try karein.","retry_after":42}}
```
Success responses me bhi `X-RateLimit-Limit/Remaining` headers.

### Extra abuse protection
- Nginx `limit_req` (IP burst) pehli layer; Cloudflare/WAF optional.
- Repeated 429 → temporary IP ban (fail2ban / WAF rule).
- Flutter Dio interceptor: 429 pe `Retry-After` padh ke UI me countdown dikhaye, auto-retry nahi OTP pe.

---

## D. Plain-PHP building blocks (Laravel ke bina ye hum khud banate hain)

| Block | Design |
|---|---|
| **RateLimiter** | Redis Lua script: `INCR key` + pehli baar `EXPIRE` (atomic). Key `rl:{group}:{user_or_ip}:{window}`. Returns remaining + retry-after. Redis down ho to **fail-closed** OTP/login pe, **fail-open** catalog GET pe. |
| **Paginator** | `offset(QueryBuilder, page, per_page)` → `LIMIT/OFFSET` + `COUNT(*)`; `cursor(QueryBuilder, cursor, limit)` → `WHERE (sort_col, id) > (?, ?)` keyset, cursor = base64(JSON). Clamp config se. |
| **Queue** | Redis list. `Queue::push(Job, queue)` → `LPUSH`; `bin/worker.php` `BRPOPLPUSH` (reliable, processing list), retry 3x backoff, fail → `failed_jobs` table. Supervisor worker restart karta hai. |
| **Scheduler** | System cron har minute `php bin/schedule.php`; wahan commands ki table (daily 08:00 expiry, hourly refill, 5-min unpaid-expire, nightly low-stock). Lock Redis `SET NX` taaki 2 server pe double run na ho. |
| **Migrations** | `backend/database/migrations/NNN_name.sql` + `schema_migrations` table; `bin/migrate.php` sirf naye files chalata hai, transaction me. |
| **Auth** | JWT access 15 min + rotating refresh (reuse detect → saare tokens revoke). Web: access JWT memory me + refresh HttpOnly SameSite=Strict cookie + OriginCheck (backend me koi PHP session nahi). |
| **Validation** | `Validator::make($data, ['phone'=>'required|phone', ...])` → `ValidationException` → 422 envelope with `fields`. |
| **Templates** | `View::render('emails/otp', $data)` — sirf email/PDF ke liye, har output `e()` se. Web pages frontend/ me hain. |
| **Cache** | `Cache::remember(key, ttl, fn)` Redis; tag ki jagah key-prefix versioning (`catalog:v{n}`; badla to n++). |
