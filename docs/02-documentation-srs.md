# 02 — Product & Technical Documentation

## 1. Roles
| Role | Access |
|---|---|
| `customer` | Store browse, cart, order, prescription upload, profile |
| `pharmacist` | Prescription verify, order pack/ship, inventory view/edit |
| `admin` (owner) | Sab kuch: inventory, orders, customers, settings, reports |
| `staff` | Inventory + order handling (settings/customers delete nahi) |

Login: Phone ya Email → OTP. Role DB me `users.role`. Customer aur owner same login screen use karte hain; login ke baad role se redirect.

## 2. Feature list (aapki requirement + original file)

### 2.1 Frontend / UX
1. **Premium 3D-styled UI** — layered soft shadows, perspective tilt cards, glass header, gradient hero, micro-animations. Heavy WebGL nahi (performance); CSS 3D + transform. Original ki emerald theme, rounded-xl/full buttons, dark/light mode.
2. **Responsive** — mobile-first, breakpoints sm/md/lg/xl.
3. **Product catalog** — category chips (Tablet, Syrup, Injection, Ointment, Capsule, Drops, Inhaler, Powder + more), filter (category, brand, Rx/OTC, price, in-stock), sort, grid/list.
4. **Smart search** — debounce 250ms, min 2 chars, suggestions dono pe: **medicine name** aur **salt/composition** (e.g. "paracetamol" → Dolo 650, Crocin…). Match highlight (original me yellow highlight hai). Recent searches.
5. **Prescription upload** — Rx-required medicine cart me ho to checkout pe upload (JPG/PNG/PDF, max 5 MB, max 5 files). Camera + gallery (app). Status: Pending / Approved / Rejected(+reason).
6. Language **English/Hindi**, Dark/Light.

### 2.2 Backend / Admin
1. **Auth** — OTP (SMS/Email), 6 digit, expiry 5 min, 5 attempts, resend cooldown 60s. Access token = JWT (15 min, `firebase/php-jwt`) + rotating refresh token (DB me sirf sha256 hash, `refresh_tokens` table).
2. **Inventory** (original features):
   - Medicine master: name, generic name, company, category, Rx flag, schedule (H/H1/X/OTC), salts, GST %, image.
   - Batches: batch no., mfg date, exp date, qty, purchase price, MRP, selling price, supplier, rack, shelf, notes.
   - Validations: expiry > mfg date; duplicate batch for same medicine blocked; qty ≥ 0; MRP ≥ purchase price.
   - Stock tracking: total units, low-stock (threshold setting), stock value.
   - **Expiry alerts**: Expiring in 30d / 90d, Expired list (Return/Dispose action), daily scheduled alert + push to owner.
   - Racks & Shelves, medicines-in-rack view.
   - CSV import with preview + validation report; CSV export; print view.
3. **Order management** — Pending → Confirmed → Packed → Shipped → Delivered; Cancelled (from Pending/Confirmed/Packed). Status history, notes, tracking id/courier.
4. **Prescription verification** — queue, image viewer/zoom, approve / reject (reason), per-item approve, pharmacist name+reg no. stamped on order.
5. **Payments** — UPI, Credit/Debit card, Net Banking (Razorpay), **COD**. Webhook verified, idempotent, refund support.
6. **Invoice** — auto PDF on `Confirmed` (GST invoice number `INV-YYYY-000001`), download in web/app, email/SMS link.
7. **Customer management** — list, search, orders, addresses, block/unblock.
8. Dashboard (original): 6 stat cards (Total Medicines, Total Units, Low Stock, Expiring 30d, Expired, Stock Value) + orders today/revenue, Stock by Category donut, Expiry Overview bars, Recently Added, Active Alerts.

### 2.3 Android App (Flutter)
- Customer mode: sab store features + biometric/PIN lock (optional).
- Owner mode: dashboard, inventory, expiry, racks, order status update, rx review.
- **Push (FCM)**: order status updates, shipping, delivery, rx approved/rejected, low stock/expiry (owner), **refill reminders** (customer ke past orders ke course-days se).

## 3. Order flow rules
```
PENDING → CONFIRMED → PACKED → SHIPPED → DELIVERED
   └────────┴──────────┴──→ CANCELLED
```
- Order create: stock **reserve** (FEFO: sabse jaldi expiry wala batch pehle; expired batch kabhi nahi).
- Rx item hai → `rx_status = pending`; pharmacist approve kare tab hi `CONFIRMED` allowed.
- Online payment: `payment_status = paid` hone ke baad hi order confirm hota hai (webhook).
- COD: confirm ke baad; delivery pe `paid`.
- Cancel → stock wapas (batch me restore) + refund (agar paid).
- Har transition `order_status_history` me + event → push/SMS.
- Invalid transition → 422.

## 4. API (v1) — summary
Response envelope:
```json
{ "success": true, "data": {...}, "meta": {"pagination": {...}}, "message": null }
{ "success": false, "error": {"code":"VALIDATION_FAILED","message":"...","fields":{...}} }
```
### Public / Auth
| Method | Path | Note |
|---|---|---|
| GET | /brand | naam, logo urls, colors (ETag cache) |
| POST | /auth/otp/request | `{identifier, type: phone\|email}` |
| POST | /auth/otp/verify | → access+refresh token, user, role |
| POST | /auth/refresh · /auth/logout | |
### Store (customer)
| GET | /categories · /medicines · /medicines/{id} | filters+pagination |
| GET | /search/suggest?q= | medicine + salt suggestions |
| GET/POST/PATCH/DELETE | /cart, /cart/items | |
| CRUD | /addresses | |
| POST | /prescriptions | multipart |
| GET | /prescriptions · /prescriptions/{id} | |
| POST | /orders | idempotency key header |
| GET | /orders · /orders/{id} · POST /orders/{id}/cancel · POST /orders/{id}/reorder | |
| GET | /orders/{id}/invoice | PDF |
| POST | /payments/create · /payments/verify | Razorpay |
| POST | /webhooks/razorpay | signature verify |
| GET | /notifications · POST /notifications/read | |
| POST/DELETE | /devices | FCM token register |
| GET/POST | /refills | reminders |
### Admin (`/admin/...`)
| GET | /dashboard/stats | |
| CRUD | /medicines, /medicines/{id}/batches, /racks, /shelves, /suppliers | |
| GET | /expiring?days=30 · /expired · /low-stock | |
| POST | /import/csv (preview + commit) · GET /export/csv | |
| GET/PATCH | /orders, /orders/{id}/status | |
| GET/PATCH | /prescriptions, /prescriptions/{id}/review | |
| GET/PATCH | /customers | |
| GET | /invoices | |
| GET/PUT | /settings (shop name, owner, low-stock threshold, GST no., drug license no.) | |
| POST | /backup | |

## 5. Security
- OTP hashed (bcrypt) DB me; plain kabhi nahi. Brute-force lock.
- HTTPS only, HSTS, secure+httpOnly cookies (web), tokens secure storage (app).
- Prescription files **private storage**, signed URL (5 min), kisi public URL pe nahi.
- Card data kabhi server pe nahi (Razorpay hosted) → PCI scope minimal.
- Webhook signature + idempotency. Order create idempotency key.
- Validation `Core\Validator` (har endpoint pe), SQL injection safe (**sirf PDO prepared statements**, string concat mana), XSS: frontend me user data sirf `textContent` (innerHTML nahi) + CSP header, backend templates (email/PDF) me `e()`; CORS whitelist; security headers middleware (HSTS, X-Frame-Options, nosniff, CSP).
- **Web auth**: access JWT sirf JS memory me (localStorage me nahi), refresh token HttpOnly+Secure+**SameSite=Strict** cookie (path `/api/v1/auth`) + `OriginCheck` middleware (Origin/Host match) → CSRF se bachav. Mobile me refresh token secure storage me.
- Framework ka safety-net nahi hai, isliye: koi bhi query `Repositories/` ke bahar nahi, `display_errors=Off` production me, `storage/` aur `config/` web-root ke bahar. Frontend aur backend alag folders hain: frontend sirf static files hai, koi secret frontend me nahi.
- `RequireRole` middleware + `Policies/` (ownership checks — user B user A ka order/rx/invoice na dekhe); audit_logs (kisne stock/price/status badla).
- File upload: mime sniff, size cap, random filename, virus-scan hook.
- Personal data: DPDP Act 2023 — consent, delete-account request, minimal collection.
- Secrets `.env` me, repo me nahi.

## 6. Compliance note (India) — launch se pehle verify karein
- Prescription-only (Schedule H/H1/X) bina valid Rx ke nahi bechni; Schedule X aur H1 ke liye extra record-keeping.
- Registered pharmacist verification aur drug license number invoice/site pe.
- Online pharmacy ke liye state/central rules (e-pharmacy draft rules/notifications) change hote rehte hain — legal advisor se confirm karein.
- Ye document legal advice nahi hai.

## 7. Non-functional targets
- API p95 < 300 ms (cached catalog < 100 ms), search suggest < 150 ms.
- 99.9% uptime target, daily DB backup + 7-day PITR.
- Web Lighthouse ≥ 90 (mobile). App cold start < 2.5 s.
- Accessibility: contrast AA, labels, 44px touch targets.

## 8. Testing
- Backend: PHPUnit — auth OTP, stock FEFO, order state machine, payment webhook, rate limit, pagination.
- Flutter: unit + widget + integration (login→order).
- E2E: Playwright (web critical path).
- CI: lint, tests, `node brand/sync.mjs --check` (generated files outdated na hon).
