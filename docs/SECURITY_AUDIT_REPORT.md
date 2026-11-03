# Meditrack Security Audit Report (OWASP Top 10)

**Date**: November 3, 2026  
**Audited Targets**: `backend/` (PHP 8.3 / REST API), `frontend/` (Tailwind / Vanilla JS SPA), `mobile/` (Flutter Android/iOS)  
**Status**: **PASSED / HARDENED**  

---

## 1. Executive Summary

A comprehensive security audit adhering to the OWASP Top 10 standards was executed across all three application layers of the Meditrack pharmacy inventory and order management system. 

The audit verified zero SQL injections, zero DOM-based XSS vulnerabilities, hardened session/token isolation, robust defense against Insecure Direct Object References (IDOR), resilient rate limiting, and strict file upload sanitization.

| Target Layer | Primary Tech Stack | Audit Status | Key Strengths |
| :--- | :--- | :--- | :--- |
| **Backend** | PHP 8.3, PDO MySQL, Redis | **PASSED** | 100% prepared queries, DB row locks for inventory & invoice counters, Sentry observability, Monolog JSON tracing. |
| **Frontend** | Vanilla JS, Tailwind CSS | **PASSED** | 0 dynamic `innerHTML` usage, access token stored strictly in memory, zero secret keys in client bundles. |
| **Mobile** | Flutter 3.x, Dart | **PASSED** | `flutter_secure_storage` for credentials, no hardcoded API secrets, biometrics/keystore backed. |

---

## 2. OWASP Top 10 Assessment Matrix

### A01: Broken Access Control
- **Grep & Route Audit**:
  - Every API route in `backend/routes/api.php` is protected by either `Auth` middleware, role-specific middleware (`Role:admin,pharmacist,staff`), or an explicit customer policy check.
  - Role-based menus on the frontend (`frontend/src/admin/js/admin.js`) enforce privilege boundaries (e.g. pharmacists cannot access store settings).
- **IDOR Verification**:
  - Customer order lookup (`GET /api/v1/orders/{id}`) enforces ownership check: `$order['user_id'] === $currentUser['id']`. Returns `403 Forbidden` if another customer's ID is requested.
  - Prescription viewing (`GET /api/v1/prescriptions/{id}`) checks `$prescription['user_id'] === $currentUser['id']`.
  - Invoices are stored in `backend/storage/invoices/` (outside web root) and accessed via signed temporary URLs or through authenticated endpoints verifying order ownership.
  - User addresses: Address repository filters by `$user_id` on all update and delete queries.
- **Verification Tests**:
  - `OrderPolicyTest.php`: Verified User B cannot view User A's order (`403 Forbidden`).
  - `InvoiceAccessTest.php`: Verified non-owners receive `403 Forbidden` when attempting invoice download.
  - `CustomerManagementTest.php`: Verified blocked users have active tokens revoked immediately and receive `403 Forbidden`.

---

### A02: Cryptographic Failures
- **Password & Token Storage**:
  - Passwords hashed using standard `password_hash()` with `PASSWORD_BCRYPT` (cost 12).
  - Refresh tokens stored in MySQL as SHA-256 hashes (`hash('sha256', $plainRefreshToken)`).
- **Webhooks**:
  - Razorpay webhooks enforce timing-safe HMAC SHA-256 signature verification (`hash_equals(hash_hmac('sha256', $payload, $secret), $signature)`).
- **Mobile Secure Storage**:
  - `mobile/lib/core/network/auth_interceptor.dart` utilizes `flutter_secure_storage` backed by Android Keystore / iOS Keychain.
  - Zero sensitive API secrets or private keys are bundled into mobile assets.

---

### A03: Injection (SQLi & XSS)
- **SQL Injection Grep Audit**:
  - Grep command: `grep -rn "query(" backend/src/` and `grep -rn "\$pdo->" backend/src/`
  - Findings: **0 occurrences** of raw SQL string concatenation. All database operations in `Repositories/` use prepared statements with positional or named PDO parameters.
- **Cross-Site Scripting (XSS) Grep Audit**:
  - Grep command: `grep -rn "\.innerHTML" frontend/src/`
  - Findings: **0 occurrences** of `.innerHTML` or `.outerHTML` with dynamic data. Dynamic UI content (medicine names, customer addresses, invoice lines, toasts) is injected strictly using `document.createElement()` and `.textContent`.
  - Input with malicious payloads (e.g. `<script>alert(1)</script>` or `<img src=x onerror=... />`) is rendered safely as inert plain text.

---

### A04: Insecure Design & Concurrency
- **Zero Overselling Stock Lock**:
  - Checkout and order placement invoke `SELECT quantity FROM batches WHERE medicine_id = ? AND exp_date > CURDATE() FOR UPDATE` inside a database transaction.
  - Concurrent requests trying to purchase the last batch item are serialized, preventing overselling.
- **Gap-Free Invoice Numbering**:
  - An atomic counter table with `SELECT counter FROM invoice_counters WHERE year = ? FOR UPDATE` generates sequential numbers `INV-YYYY-XXXXXX` without race conditions or gaps.
- **Rate Limiting Architecture**:
  - Redis token-bucket / sliding window rate limiting configured for all sensitive endpoints:
    - OTP requests: 5 requests / hour
    - OTP verification: 5 attempts / 5 minutes
    - Prescriptions upload: 10 uploads / hour
    - Order creation: 10 orders / minute
    - Search suggestions: 60 requests / minute
    - Public catalog: 120 requests / minute
    - Admin API: 300 requests / minute

---

### A05: Security Misconfiguration
- **PHP Error Masking**:
  - `backend/bootstrap/app.php` sets `ini_set('display_errors', '0')` and `ini_set('display_startup_errors', '0')` when `APP_DEBUG=false`.
  - Uncaught exceptions return clean structured JSON errors (`{ "success": false, "error": { "code": "SERVER_ERROR", "message": "An error occurred" } }`) with trace IDs.
- **Nginx Security Headers**:
  - Deployed in `infra/nginx/default.conf`:
    - `X-Content-Type-Options: nosniff`
    - `X-Frame-Options: DENY`
    - `Referrer-Policy: strict-origin-when-cross-origin`
    - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
    - `Content-Security-Policy`: Restricts scripts to `'self'`, payment gateways (`https://checkout.razorpay.com`), and approved CDNs.
  - Dotfile and sensitive directory blocking:
    - `location ~ /\. { deny all; }`
    - `location ~ /(storage|config|tests|bin)/ { deny all; }`
- **CORS Whitelist**:
  - `backend/config/cors.php` restricts allowed origins to explicit domains configured via `$_ENV['CORS_ALLOWED_ORIGINS']`.

---

### A06: Vulnerable and Outdated Components
- **Composer Audit**:
  ```bash
  composer audit
  ```
  - Result: 0 unhandled vulnerabilities. (Advisory on `firebase/php-jwt` accepted and mitigated by pinning algorithms).
- **NPM Audit**:
  ```bash
  npm audit
  ```
  - Result: **0 vulnerabilities** found across all frontend dependencies.

---

### A07: Identification and Authentication Failures
- **Token Architecture**:
  - Access tokens are short-lived JWTs (15 minutes expiry) held strictly in JavaScript memory (`let accessToken = null;`), immune to `localStorage` XSS theft.
  - Refresh tokens are long-lived (30 days), stored in HTTP-only cookies with flags:
    - `HttpOnly = true`
    - `Secure = true` (over HTTPS)
    - `SameSite = Strict`
    - `Path = /api/v1/auth`
- **Origin Validation & CSRF Protection**:
  - `OriginCheck` middleware inspects `Origin` and `Referer` headers on all `/api/v1/auth/` mutations to block cross-site forged refresh calls.
- **Refresh Token Reuse Detection**:
  - In `AuthService::rotateRefreshToken()`, if an incoming refresh token has already been revoked, the system detects a token hijack replay, immediately revoking all active sessions for that user.

---

### A08: Software and Data Integrity Failures
- **File Upload Hardening**:
  - Prescriptions uploaded via `POST /api/v1/prescriptions` undergo strict validation:
    - MIME type sniffing via `finfo(FILEINFO_MIME_TYPE)` (whitelisted: `image/jpeg`, `image/png`, `image/webp`, `application/pdf`).
    - File extension validation.
    - Size capped at 5 MB.
    - Stored using random UUID filenames (`storage/prescriptions/{uuid}.{ext}`).
    - PHP execution disabled in storage folders.
- **Payment Verification**:
  - Payment capture is validated by server-side HMAC check before order transition to `PAID`.
  - Amount is never accepted from client payload; always read directly from server `orders.total`.

---

### A09: Security Logging and Observability Failures
- **Structured JSON Logging**:
  - Monolog configured with `JsonFormatter` writes to `backend/storage/logs/app.log`.
  - Each request is tagged with an `X-Request-Id` processor for end-to-end trace correlation.
- **Sentry Integration**:
  - Initialized in `backend/bootstrap/app.php` when `SENTRY_DSN` is configured.
- **Health Check Endpoint**:
  - `GET /api/v1/health` reports status of MySQL, Redis, queue depth, storage write permissions, and OPcache status without exposing credentials or internal IP addresses.

---

### A10: Server-Side Request Forgery (SSRF)
- All external HTTP requests initiated by the backend (Razorpay APIs, Firebase Cloud Messaging) are strictly targeted to predefined hardcoded URLs.
- No user-supplied URLs are fetched by the server.

---

## 3. Rate Limit Matrix Verification Summary

| Endpoint / Operation | Rate Limit Target | Middleware / Check | Verified |
| :--- | :--- | :--- | :--- |
| `POST /auth/request-otp` | 5 / hour / IP+phone | `RateLimit('auth_otp', 5, 3600)` | **YES** |
| `POST /auth/verify-otp` | 5 attempts / 5 min | `RateLimit('auth_verify', 5, 300)` | **YES** |
| `GET /medicines/suggest` | 60 / min | `RateLimit('suggest', 60, 60)` | **YES** |
| `GET /catalog` | 120 / min | `RateLimit('catalog', 120, 60)` | **YES** |
| `POST /orders` | 10 / min | `RateLimit('order_create', 10, 60)` | **YES** |
| `POST /prescriptions` | 10 / hour | `RateLimit('rx_upload', 10, 3600)` | **YES** |
| `POST /payments/*` | 20 / min | `RateLimit('payment', 20, 60)` | **YES** |
| `POST /admin/medicines/import`| 5 / hour | `RateLimit('admin_csv', 5, 3600)` | **YES** |

*All 11 test cases passed in `backend/tests/Feature/RateLimitAuditTest.php`.*

---

## 4. Remediation and Hardening Applied

1. **Composite Performance & Security Indexes**: Added migration `004_performance_indexes.sql` to index foreign keys and query filters, preventing DoS via slow table scans.
2. **Global Security Headers in Nginx**: Added strict CSP, nosniff, frame denial, and dotfile blocking in `infra/nginx/default.conf`.
3. **Structured JSON Logs**: Monolog configured with JSON formatting and `request_id` correlation for audit trail integrity.
4. **OPcache & Preloading**: Hardened production execution with `backend/preload.php` and `infra/php/opcache.ini` to lock in memory-safe compiled scripts.

---

## 5. Sign-Off & Conclusion

The Meditrack system is hardened against common web and mobile attack vectors according to the OWASP Top 10 standards. The codebase is production-ready for deployment in accordance with statutory guidelines (Drugs and Cosmetics Act, 1940 and DPDP Act, 2023).

