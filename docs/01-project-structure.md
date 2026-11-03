# 01 — Project Structure (3 alag folders)

Root par teen independent project: **`frontend/`**, **`backend/`**, **`mobile/`**. Teeno sirf `/api/v1` (JSON) ke through baat karte hain. Shared cheezein (`brand/`, `docs/`, `infra/`, `reference/`) root par.

```
meditrack/
├── frontend/     → Web (storefront + admin): HTML + Tailwind + vanilla JS   (static files)
├── backend/      → Plain PHP 8.3 JSON API + MySQL + Redis + workers          (koi HTML page nahi)
├── mobile/       → Flutter (Dart) Android app
├── brand/        → ★ naam/logo/color ka single source (teeno ko feed karta hai)
├── docs/         → documentation
├── infra/        → docker, nginx, supervisor, cron, CI
├── reference/    → original Meditrack.html (UI reference)
├── AGENTS.md  README.md  docker-compose.yml  .env.example
```

---

## 1. `frontend/` — HTML + Tailwind + vanilla JS
Static site. Backend se **sirf fetch(`/api/v1/...`)** se data leta hai. Build ke baad `dist/` nginx serve karta hai.

```
frontend/
├── package.json                  # tailwindcss CLI, scripts: dev, build, brand:sync, test:e2e
├── .env.example                  # API_BASE_URL=/api/v1
├── build.mjs                     # partials include + brand inject + assets copy → dist/
├── tailwind.config.js            # presets: [require('./tailwind.brand.cjs')], darkMode:'class'
├── tailwind.brand.cjs            # ← AUTO-GENERATED (brand/sync.mjs)
├── src/
│   ├── partials/                 # head.html, header.html, footer.html, bottom-nav.html,
│   │                             # admin-sidebar.html, admin-topbar.html   (<!-- @include partials/header.html -->)
│   ├── index.html                # Home
│   ├── catalog.html  medicine.html  cart.html  checkout.html
│   ├── orders.html  order.html  upload-rx.html  profile.html  notifications.html  login.html
│   ├── admin/
│   │   ├── index.html            # Dashboard
│   │   ├── medicines.html  expiring.html  expired.html  racks.html
│   │   ├── orders.html  prescriptions.html  customers.html  invoices.html  settings.html
│   ├── css/
│   │   ├── app.css               # tailwind directives + 3D utilities
│   │   └── brand.css             # ← AUTO-GENERATED (CSS variables, dark mode)
│   ├── js/
│   │   ├── brand.generated.js    # ← AUTO-GENERATED (window.BRAND fallback)
│   │   ├── core/                 # config.js, api.js (fetch wrapper, 401 refresh, 429 handling),
│   │   │                         # auth.js (token in memory), guard.js (role redirect), brand.js (runtime /brand),
│   │   │                         # i18n.js, theme.js, dom.js (safe render helpers: textContent only),
│   │   │                         # toast.js, modal.js, paginator.js, debounce.js
│   │   ├── ui/                   # tilt-3d.js, search-suggest.js, skeleton.js, charts.js (inline SVG)
│   │   ├── store/                # home.js catalog.js medicine.js cart.js checkout.js orders.js ...
│   │   └── admin/                # dashboard.js medicines.js expiring.js racks.js orders.js rx-review.js ...
│   ├── lang/                     # en.json, hi.json (original file ke en/hi strings)
│   └── assets/                   # images, fonts, illustrations (brand/assets build me assets/brand/ me copy hota hai)
├── dist/                         # build output (gitignored) → nginx root
└── tests/e2e/                    # Playwright
```

**Rules:** user data kabhi `innerHTML` me nahi (sirf `textContent` / `dom.js` helpers). Koi hardcoded naam/color nahi — `window.BRAND`, Tailwind brand tokens, CSS vars.
**SEO note:** static + JS render me medicine pages ka SEO weak hota hai. Agar baad me SEO chahiye: `backend` se ek chhota prerender/sitemap endpoint ya `frontend/build.mjs` me popular medicines ke static pages generate karo.

---

## 2. `backend/` — Plain PHP 8.3 API (framework nahi)
Sirf JSON, webhooks, PDF/file serve aur background workers. Koi HTML page nahi (sirf email/PDF templates).

```
backend/
├── composer.json                 # fast-route, phpdotenv, php-jwt, predis, phpmailer, dompdf, razorpay, kreait, monolog, phpunit
├── .env.example
├── openapi.yaml                  # API spec
├── public/
│   ├── index.php                 # ★ front controller (web-root yehi folder)
│   └── .htaccess                 # Apache ke liye (nginx me try_files)
├── bootstrap/
│   ├── app.php                   # env, error handler (Monolog), container
│   └── helpers.php               # e(), brand(), t()  (email/PDF templates ke liye)
├── config/
│   ├── app.php database.php redis.php mail.php payment.php fcm.php sms.php cors.php
│   ├── brand.php                 # ← AUTO-GENERATED
│   ├── rate_limits.php           # saari limits ek jagah
│   └── pagination.php
├── routes/
│   └── api.php                   # /api/v1 (groups + middleware) + /webhooks
├── src/
│   ├── Core/                     # apna mini-framework
│   │   ├── Router.php Request.php Response.php ApiResponse.php Container.php
│   │   ├── Database.php QueryBuilder.php Validator.php Paginator.php
│   │   ├── RateLimiter.php Cache.php Queue.php Lang.php View.php Jwt.php
│   │   └── Exceptions/
│   ├── Middleware/               # ForceJson, Cors, SecurityHeaders, OriginCheck, Auth, RequireRole,
│   │                             # RateLimit, Idempotency, SetLocale
│   ├── Enums/                    # OrderStatus PaymentStatus PaymentMethod RxStatus Role Schedule
│   ├── Controllers/
│   │   ├── Api/V1/
│   │   │   ├── Auth/             # OtpController, TokenController
│   │   │   ├── Store/            # Catalog Search Cart Checkout Order Prescription Address
│   │   │   │                     # Payment Notification Refill Brand
│   │   │   └── Admin/            # Dashboard Medicine Batch Rack Expiry OrderAdmin PrescriptionReview
│   │   │                         # Customer Invoice ImportExport Setting
│   │   └── Webhooks/             # RazorpayWebhookController
│   ├── Repositories/             # ★ SQL sirf yahin (PDO prepared)
│   ├── Services/                 # Otp Auth Stock(FEFO) Order(state machine) Prescription Invoice Push Search Brand
│   │   ├── Payment/              # PaymentGateway, RazorpayGateway, CodGateway
│   │   └── Sms/                  # SmsProvider, Msg91Provider, LogProvider
│   ├── Jobs/                     # SendOtpSms SendPush GenerateInvoicePdf ImportMedicinesCsv
│   ├── Events/                   # OrderStatusChanged + listeners
│   └── Policies/                 # Order/Prescription/Invoice ownership checks
├── templates/                    # sirf email + PDF
│   ├── emails/{otp.php, order-update.php}
│   └── invoices/pdf.php
├── lang/{en,hi}/                 # API messages, email/SMS text
├── database/
│   ├── schema.sql                # baseline schema
│   ├── migrations/               # 001_initial.sql, 002_*.sql ...
│   └── seeds/                    # categories.sql, demo_medicines.php
├── bin/                          # migrate.php seed.php worker.php schedule.php
├── commands/                     # ExpiryAlert RefillReminder ExpireUnpaidOrders LowStockDigest
├── storage/                      # PRIVATE: prescriptions/ invoices/ logs/ backups/
└── tests/{Unit,Feature}/         # PHPUnit
```

---

## 3. `mobile/` — Flutter (Dart)
```
mobile/
├── pubspec.yaml
├── flutter_launcher_icons.yaml   # ← AUTO-GENERATED
├── android/app/src/main/res/values/strings.xml   # ← AUTO-GENERATED
└── lib/
    ├── main.dart  app.dart
    ├── core/{brand (brand_tokens.dart ← AUTO-GENERATED, brand_controller.dart), theme, network, storage, i18n, push, utils}/
    ├── features/{auth, home, catalog, search, cart, checkout, prescription, orders, payments,
    │             notifications, profile, owner}/          # owner = admin/pharmacist mode
    ├── shared/widgets/           # Card3D, PrimaryButton, StatCard, SkeletonBox, StatusChip
    └── router/app_router.dart
```

---

## 4. Teeno ke beech contract
| | frontend | backend | mobile |
|---|---|---|---|
| Baat karte hain | `fetch /api/v1` | JSON API | `dio /api/v1` |
| Auth | access JWT **memory** me + refresh token **HttpOnly SameSite=Strict cookie** (`/api/v1/auth` path) | dono tarah support: cookie (`client=web`) ya body (`client=mobile`) | access+refresh `flutter_secure_storage` me |
| Brand | `brand.generated.js` + runtime `/brand` | `config/brand.php` + `/brand` endpoint | `brand_tokens.dart` + runtime `/brand` |
| Deploy | static files (nginx/CDN) | php-fpm + workers | APK/AAB |

## 5. Deploy (same domain, CORS ki zaroorat nahi)
```
https://meditrack.example/        → frontend/dist   (nginx root)
https://meditrack.example/api/    → backend/public/index.php (php-fpm)
```
Dev me alag ports (frontend :5173, backend :8080) — `backend/config/cors.php` whitelist me sirf wahi origins, aur frontend dev server `/api` ko proxy kare.

## 6. Naming / convention
- API prefix `/api/v1`; admin `/api/v1/admin/...`. Breaking change → `/api/v2`.
- SQL sirf `backend/src/Repositories/`, hamesha prepared. Controllers patle, logic Services me.
- Web-root sirf `backend/public/` aur `frontend/dist/`. `storage/`, `config/`, `src/` kabhi public nahi.
- Frontend backend ka code import nahi karta, aur backend frontend ka — sirf API contract (`backend/openapi.yaml`).
