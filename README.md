# MediTrack — Full-Stack Online Pharmacy & Inventory OS

<div align="center">

![MediTrack Banner](brand/assets/logo.svg)

**A high-performance, framework-free Online Pharmacy Platform and Pharmacy Inventory Operating System built for compliance, scale, and lightning-fast customer experience.**

[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Tailwind CSS 3](https://img.shields.io/badge/TailwindCSS-3.4-38B2AC?style=flat&logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![MySQL 8](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-6.x%2F7.x-DC382D?style=flat&logo=redis&logoColor=white)](https://redis.io/)
[![Flutter](https://img.shields.io/badge/Flutter-Ready-02569B?style=flat&logo=flutter&logoColor=white)](https://flutter.dev/)
[![Tests Passing](https://img.shields.io/badge/Tests-167%20Passed-success?style=flat&logo=github-actions&logoColor=white)](#testing--verification)
[![License: Proprietary](https://img.shields.io/badge/License-Proprietary-blue.svg)](#)

</div>

---

## 📋 Table of Contents
- [Project Overview](#-project-overview)
- [Architecture & Tech Stack](#-architecture--tech-stack)
- [Single Source of Brand Identity](#-single-source-of-brand-identity)
- [Core Workflows](#-core-workflows)
  - [1. Authentication & Session Rotation](#1-authentication--session-rotation)
  - [2. FEFO Inventory & Order Lifecycle](#2-fefo-inventory--order-lifecycle)
  - [3. Prescription Verification Workflow](#3-prescription-verification-workflow)
  - [4. Payment & Webhook Idempotency](#4-payment--webhook-idempotency)
  - [5. Concurrency-Safe GST Invoicing](#5-concurrency-safe-gst-invoicing)
  - [6. DPDP Act 2023 Customer Management](#6-dpdp-act-2023-customer-management)
- [Complete API Reference (`/api/v1`)](#-complete-api-reference-apiv1)
- [Directory Structure](#-directory-structure)
- [Getting Started & Local Setup](#-getting-started--local-setup)
- [Testing & Verification](#-testing--verification)

---

## 🌟 Project Overview

**MediTrack** is an enterprise-grade, end-to-end pharmaceutical eCommerce and inventory management solution. It bridges customer medicine ordering with strict Indian Drugs & Cosmetics Act regulations, pharmacy inventory FEFO (First-Expiry-First-Out) fulfillment, automated GST compliance, and the Digital Personal Data Protection (DPDP) Act 2023.

### Key Highlights
- **Zero Monolithic Backend Frameworks**: Built using plain PHP 8.3 with a custom, high-speed PSR-4 mini-framework (`src/Core`), offering maximum execution speed, microscopic memory footprint, and complete control over security headers and SQL execution.
- **Strict Separation of Concerns**:
  - `frontend/`: Static HTML5 + Tailwind CSS + Vanilla ES Modules. Zero dynamic `innerHTML` (XSS-safe DOM builder).
  - `backend/`: Plain PHP 8.3 JSON REST API serving `/api/v1` endpoints with pure prepared PDO statements.
  - `mobile/`: Flutter (Dart) mobile application consuming the identical REST API with role-swapping (Customer vs Pharmacist mode).
- **Hardened Security & Privacy**: HttpOnly SameSite=Strict cookies for web refresh tokens, 15-minute JWT access tokens, automatic token theft & reuse detection, signed HMAC download URLs for medical records, and rate limiting with Redis Lua scripts.

---

## 🛠 Architecture & Tech Stack

```
┌────────────────────────────────────────────────────────────────────────┐
│                        MediTrack Ecosystem                             │
├─────────────────────────┬──────────────────────────────────────────────┤
│  Frontend (Web)         │ Static HTML5, Tailwind CSS, Vanilla JS       │
│  Mobile App             │ Flutter (Dart) — Customer & Owner Modes      │
│  API Gateway & Router   │ Nginx Reverse Proxy -> FastRoute (PHP 8.3)   │
│  Backend Services       │ Plain PHP 8.3 (MediTrack\Core Mini-Framework)│
│  Data Layer             │ MySQL 8.0 (InnoDB) + Redis (Cache, Queues)   │
│  Background Workers     │ Plain PHP CLI Worker & Scheduler             │
│  Third-Party Services   │ Razorpay (Payments), Msg91 (SMS), PHPMailer  │
└─────────────────────────┴──────────────────────────────────────────────┘
```

| Component | Technology | Description |
|---|---|---|
| **Backend Core** | PHP 8.3 | Custom micro-framework with DI Container, Router (`nikic/fast-route`), PDO Wrapper, QueryBuilder, and Validator |
| **Database** | MySQL 8.0 | Strict InnoDB, Foreign Keys, JSON columns, Full-Text indexing for medicines & composition salts |
| **Caching & Queues**| Redis 6/7 (`predis/predis`)| Versioned catalog cache, atomic Lua rate-limiting, and FIFO queues |
| **Authentication** | JWT (`firebase/php-jwt`) | 15-minute short-lived access tokens + 30-day rotating refresh tokens |
| **PDF Generation** | `dompdf/dompdf` | Server-side rendering of GST Tax Invoices in compliance with Indian pharmacy laws |
| **Payment Gateway** | Razorpay SDK | UPI, Debit/Credit Cards, Net Banking + COD gateway abstraction |
| **Frontend UI** | Modern Vanilla JS | Modular ES6, Tailwind CSS CLI, custom DOM helper `el()` preventing XSS, i18n (`en`/`hi`) |
| **Infra & DevOps** | Docker & Compose | Nginx, PHP-FPM 8.3, MySQL 8, Redis, Mailpit, CLI Worker, and Scheduler Cron |

---

## 🎨 Single Source of Brand Identity

All branding attributes (names, legal entities, phone numbers, emails, color palettes, logos) originate from a single JSON file:
[`brand/brand.json`](brand/brand.json)

Running the sync script propagates brand tokens across all platforms:
```bash
node brand/sync.mjs
```
- **Backend**: Generates [`backend/config/brand.php`](backend/config/brand.php) accessible via `brand('dot.key')`.
- **Frontend**: Generates [`frontend/tailwind.brand.cjs`](frontend/tailwind.brand.cjs) and injects favicons, logos, and metadata.
- **Mobile**: Generates Flutter launcher assets and `strings.xml`.

---

## 🔄 Core Workflows

### 1. Authentication & Session Rotation
MediTrack uses a passwordless Phone/Email OTP system with automated refresh token rotation and reuse detection.

```mermaid
sequenceDiagram
    autonumber
    actor Customer as User / Client
    participant Auth as Auth API
    participant Cache as Redis
    participant DB as MySQL DB

    Customer->>Auth: POST /api/v1/auth/otp/request {identifier: "phone/email"}
    Auth->>Cache: Check Rate Limit (3 req/min)
    Auth->>DB: Store Bcrypt OTP Hash (expires in 5 min)
    Auth-->>Customer: 200 OK (OTP Sent via SMS/Email)

    Customer->>Auth: POST /api/v1/auth/otp/verify {identifier, code}
    Auth->>DB: Verify Code with hash_equals()
    Auth->>DB: Create Refresh Token (SHA-256 hash)
    Auth-->>Customer: 200 OK with Access Token (15 min) + Refresh Cookie (Web: HttpOnly)

    Note over Customer,Auth: On Token Expiry (Automatic Rotation)
    Customer->>Auth: POST /api/v1/auth/refresh
    Auth->>DB: Find token_hash. If revoked -> REVOKE ALL SESSIONS (Theft Detected!)
    Auth->>DB: Revoke old token & issue new pair
    Auth-->>Customer: New Access Token + New Refresh Token
```

---

### 2. FEFO Inventory & Order Lifecycle
Orders follow a strict First-Expiry-First-Out (FEFO) batch allocation algorithm.

```mermaid
stateDiagram-v2
    [*] --> PENDING: POST /orders (FEFO Stock Reserved)
    PENDING --> CONFIRMED: Payment Captured & Rx Approved
    CONFIRMED --> PACKED: Admin / Pharmacist Packs Items
    PACKED --> SHIPPED: Courier & Tracking Assigned
    SHIPPED --> DELIVERED: Customer Receives Order (COD Collected)
    
    PENDING --> CANCELLED: Customer or Auto-Expire (15 min unpaid)
    CONFIRMED --> CANCELLED: Customer / Admin Cancel
    PACKED --> CANCELLED: Admin Cancel Only
    
    CANCELLED --> [*]: Stock Released & Refund Triggered
    DELIVERED --> [*]: Invoice Issued & Refill Reminders Scheduled
```

---

### 3. Prescription Verification Workflow
Medicines marked `Schedule H/H1/X` mandate a registered pharmacist review before fulfillment.

```mermaid
flowchart TD
    A[Customer uploads prescription image/PDF] --> B[finfo MIME sniff + extension whitelist]
    B --> C[Stored in private storage outside web-root]
    C --> D[Prescription pending review]
    D --> E{Pharmacist Review}
    E -- Rejected --> F[Notify customer with reason + Option to cancel order]
    E -- Approved --> G[Stamp Pharmacist Name & Reg No on Order]
    G --> H[order.rx_status = 'approved']
    H --> I[Unblocks transition to CONFIRMED]
```

---

### 4. Payment & Webhook Idempotency
Online orders integrate with Razorpay. Webhooks ensure eventual consistency even if customer network drops.

```mermaid
sequenceDiagram
    autonumber
    participant App as Web / Mobile
    participant Srv as MediTrack API
    participant Rzp as Razorpay Gateway

    App->>Srv: POST /api/v1/payments/create {order_id}
    Srv->>Rzp: Create Gateway Order (enforces order.total)
    Rzp-->>Srv: Gateway Order ID
    Srv-->>App: Gateway Order Details

    App->>Rzp: Customer Completes UPI/Card Payment
    Rzp-->>App: payment_id & signature
    App->>Srv: POST /api/v1/payments/verify
    Srv->>Srv: HMAC-SHA256 Signature Verification
    Srv->>Srv: Transition Order to CONFIRMED

    Note over Rzp,Srv: Asynchronous Webhook Fallback
    Rzp->>Srv: POST /api/v1/webhooks/razorpay (payment.captured)
    Srv->>Srv: Verify Webhook Signature with hash_equals()
    Srv->>Srv: Deduplicate event via webhook_events table
    Srv->>Srv: Auto-confirm order if still pending
```

---

### 5. Concurrency-Safe GST Invoicing
When an order reaches `CONFIRMED`:
1. `GenerateInvoiceJob` executes via background queue.
2. An atomic counter row lock in `invoice_counters` assigns sequential invoice numbers: `INV-2026-000001`, `INV-2026-000002` without gaps or collisions under concurrent workers.
3. Dompdf renders [`templates/invoices/pdf.php`](backend/templates/invoices/pdf.php) with brand colors, shop GSTIN, drug license, pharmacist stamp, batch numbers, and CGST/SGST/IGST breakdown.
4. Saved in private storage `backend/storage/invoices/`. Customer downloads via `GET /orders/{id}/invoice`. Non-owners receive `403 Forbidden`.

---

### 6. DPDP Act 2023 Customer Management
- **Customer Lifetime Value (LTV)**: Real-time calculation summing all non-cancelled order totals.
- **Account Blocking**: Setting `is_blocked = 1` immediately terminates all active refresh tokens in `refresh_tokens`. Auth middleware rejects subsequent requests with `403 Forbidden` (`ACCOUNT_BLOCKED`).
- **Data Portability**: `GET /admin/customers/{id}/export` delivers a complete, portable JSON archive of user profile, saved addresses, orders, prescriptions, and refill logs.
- **Right to Erasure**: `POST /admin/customers/{id}/delete-request` tracks deletion requests with immutable audit logs.

---

## 📡 Complete API Reference (`/api/v1`)

### 🔑 Authentication & Profile
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/v1/auth/otp/request` | Request 6-digit OTP via SMS or Email | No (Rate-limited) |
| `POST` | `/api/v1/auth/otp/verify` | Verify OTP & receive JWT access + refresh tokens | No (Fail-closed) |
| `POST` | `/api/v1/auth/refresh` | Rotate refresh token & issue new access token | Cookie / Bearer |
| `POST` | `/api/v1/auth/logout` | Revoke current refresh token session | Bearer |
| `GET` | `/api/v1/auth/me` | Current authenticated user profile | Bearer |

### 🏬 Catalog & Search
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `GET` | `/api/v1/categories` | List active medicine categories | No |
| `GET` | `/api/v1/medicines` | Catalog with filters (category, rx, in_stock, price, sort) | No (Paginated) |
| `GET` | `/api/v1/medicines/{slug}` | Single medicine with earliest-expiring price & salts | No |
| `GET` | `/api/v1/search/suggest?q=` | Debounced live search suggestions (names & composition salts) | No (Cached 60s) |

### 🛒 Cart & Addresses
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `GET` | `/api/v1/cart` | Get current user's cart with subtotal & tax totals | Bearer |
| `POST` | `/api/v1/cart/items` | Add item to cart with live stock check | Bearer |
| `PUT` | `/api/v1/cart/items/{id}` | Update quantity of cart item | Bearer |
| `DELETE`| `/api/v1/cart/items/{id}` | Remove line item from cart | Bearer |
| `DELETE`| `/api/v1/cart` | Clear entire cart | Bearer |
| `GET` | `/api/v1/addresses` | List saved delivery addresses | Bearer |
| `POST` | `/api/v1/addresses` | Create delivery address | Bearer |
| `PUT` | `/api/v1/addresses/{id}` | Update delivery address | Bearer |
| `DELETE`| `/api/v1/addresses/{id}` | Remove delivery address | Bearer |

### 📦 Orders & Invoicing
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/v1/orders` | Checkout cart to order (Idempotency-Key enforced) | Bearer |
| `GET` | `/api/v1/orders` | Customer orders history (Cursor-based pagination) | Bearer |
| `GET` | `/api/v1/orders/{id}` | Detailed order status, timeline & item batches | Bearer (Owner Only) |
| `POST` | `/api/v1/orders/{id}/cancel` | Cancel order before packing (Restores stock) | Bearer (Owner Only) |
| `POST` | `/api/v1/orders/{id}/reorder` | Fast re-order items back into cart | Bearer (Owner Only) |
| `GET` | `/api/v1/orders/{id}/invoice` | Stream or download Dompdf GST Tax Invoice (PDF) | Bearer (Owner Only) |

### 📄 Prescriptions (Rx)
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/v1/prescriptions` | Multipart upload (JPG, PNG, PDF ≤ 5 MB, max 5 files) | Bearer |
| `GET` | `/api/v1/prescriptions` | List user's uploaded prescriptions | Bearer |
| `GET` | `/api/v1/prescriptions/reusable` | Fetch active reusable approved prescriptions | Bearer |
| `GET` | `/api/v1/prescriptions/{id}` | Prescription review status & metadata | Bearer (Owner Only) |
| `GET` | `/api/v1/prescriptions/{id}/files/{fId}`| HMAC signed short-lived (5 min) file streaming | Bearer (Owner Only) |

### 💳 Payments & Webhooks
| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/v1/payments/create` | Initialize Razorpay gateway order | Bearer |
| `POST` | `/api/v1/payments/verify` | Verify payment signature & capture order | Bearer |
| `POST` | `/api/v1/webhooks/razorpay` | Razorpay webhook (Signature validated with secret) | Webhook Signature |

### 🛡 Admin Operations & DPDP
| Method | Endpoint | Description | Required Role |
|---|---|---|---|
| `GET` | `/api/v1/admin/dashboard/stats` | 6 Stat cards, expiry breakdown, revenue, alerts | Admin, Pharmacist |
| `GET` | `/api/v1/admin/medicines` | Full inventory table with server-side pagination | Admin, Pharmacist |
| `POST` | `/api/v1/admin/medicines` | Add new medicine with salts & category | Admin, Pharmacist |
| `GET` | `/api/v1/admin/expiring` | Medicines expiring within 30 or 90 days | Admin, Pharmacist |
| `GET` | `/api/v1/admin/expired` | Expired batches for return or disposal | Admin, Pharmacist |
| `GET` | `/api/v1/admin/low-stock` | Medicines below minimum reorder threshold | Admin, Pharmacist |
| `GET` | `/api/v1/admin/racks` | Racks and shelves management | Admin, Pharmacist |
| `GET` | `/api/v1/admin/orders` | Filter orders by status, payment, date range | Admin, Pharmacist |
| `PATCH` | `/api/v1/admin/orders/{id}/status` | Transition order status, assign courier tracking | Admin, Pharmacist |
| `GET` | `/api/v1/admin/prescriptions` | Review queue of pending customer prescriptions | Admin, Pharmacist |
| `PATCH` | `/api/v1/admin/prescriptions/{id}/review` | Approve/Reject prescription & stamp pharmacist reg # | Admin, Pharmacist |
| `GET` | `/api/v1/admin/invoices` | List and search all generated GST invoices | Admin, Pharmacist |
| `POST` | `/api/v1/admin/invoices/{id}/resend`| Resend invoice download notification to customer | Admin, Pharmacist |
| `GET` | `/api/v1/admin/customers` | Customer list with orders count & lifetime value (LTV)| Admin |
| `GET` | `/api/v1/admin/customers/{id}` | Detailed customer profile with address & order log | Admin |
| `POST` | `/api/v1/admin/customers/{id}/block` | Block customer & immediately revoke all sessions | Admin |
| `POST` | `/api/v1/admin/customers/{id}/unblock` | Restore customer access | Admin |
| `GET` | `/api/v1/admin/customers/{id}/export` | DPDP Act 2023 portable personal data JSON export | Admin |
| `POST` | `/api/v1/admin/customers/{id}/delete-request` | Record DPDP Right to Erasure account deletion | Admin |

---

## 📂 Directory Structure

```
meditrack/
├── backend/                       # Plain PHP 8.3 JSON REST API (No framework)
│   ├── bin/                       # CLI commands: worker, scheduler, migrate, seed
│   ├── bootstrap/                 # Application bootstrap & DI Container setup
│   ├── config/                    # Database, Redis, CORS, JWT, rate limits config
│   ├── database/                  # Schema definition and incremental migrations
│   ├── lang/                      # Bilingual error and notification messages (en, hi)
│   ├── public/                    # Front controller (index.php) - Single web entry point
│   ├── src/                       # PSR-4 Autoloaded source code (MediTrack\)
│   │   ├── Controllers/           # Storefront and Admin API Controllers
│   │   ├── Core/                  # Mini-framework: Router, DB, Cache, Queue, Validator
│   │   ├── Enums/                 # PHP 8.1+ backed Enums (Role, OrderStatus, etc.)
│   │   ├── Jobs/                  # Asynchronous queue jobs (Invoice, Email, SMS)
│   │   ├── Middleware/            # Pipeline: Auth, Role, ForceJson, RateLimit, CORS
│   │   ├── Policies/              # Authorization & IDOR protection policies
│   │   ├── Repositories/          # 100% prepared PDO SQL database repositories
│   │   └── Services/              # Business logic: Order, FEFO Stock, Payment, Invoice
│   ├── storage/                   # Private storage (invoices, prescriptions, logs, backups)
│   ├── templates/                 # Dompdf invoice and email templates
│   └── tests/                     # PHPUnit Feature and Unit test suites
├── brand/                         # Single source of truth for branding
│   ├── assets/                    # SVG logos, dark variants, favicons, app icons
│   ├── brand.json                 # Core brand tokens (colors, names, contact, legal)
│   └── sync.mjs                   # Cross-platform token synchronization script
├── frontend/                      # Static Web Storefront & Admin Portal
│   ├── src/                       # Source HTML templates, partials, and Tailwind CSS
│   ├── src/js/                    # Modular Vanilla JavaScript (Auth, API wrapper, DOM)
│   ├── dist/                      # Production-ready compiled static files
│   ├── build.mjs                  # Static site builder and template compiler
│   └── package.json               # Node dependencies (Tailwind CLI, Playwright)
├── infra/                         # Production infrastructure configuration
│   └── nginx/                     # Reverse proxy routing /api -> backend, / -> frontend
├── mobile/                        # Cross-Platform Flutter Client (Dart)
└── docker-compose.yml             # Full containerized development environment
```

---

## 🚀 Getting Started & Local Setup

### Prerequisites
- **PHP 8.3+** with extensions: `pdo_mysql`, `pdo_sqlite`, `redis`, `gd`, `mbstring`, `fileinfo`, `curl`
- **Composer** (PHP package manager)
- **Node.js 18+** & npm
- **MySQL 8.0+**
- **Redis 6+**
- *(Optional)* **Docker & Docker Compose**

---

### Step-by-Step Installation

#### 1. Clone the Repository
```bash
git clone https://github.com/Rajkumar1813/meditrack.git
cd meditrack
```

#### 2. Configure Environment Variables
```bash
cp .env.example backend/.env
```
Edit `backend/.env` with your database and service credentials:
```ini
APP_ENV=development
APP_DEBUG=true
APP_KEY=base64:your-32-byte-secret-key-goes-here!

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=meditrack
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

RAZORPAY_KEY_ID=rzp_test_samplekey
RAZORPAY_KEY_SECRET=sample_secret_key
```

#### 3. Install Backend Dependencies & Run Migrations
```bash
cd backend
composer install

# Run database migrations & seed initial pharmacy catalog
php bin/migrate.php
php bin/seed.php
cd ..
```

#### 4. Sync Brand Tokens & Build Frontend
```bash
# Sync brand.json into backend, frontend, and mobile
node brand/sync.mjs

# Install frontend dependencies and compile static assets
cd frontend
npm install
npm run build
cd ..
```

#### 5. Start Background Workers & Local Server
```bash
# Terminal 1: Background Queue Worker (for Invoices & Notifications)
php backend/bin/worker.php

# Terminal 2: Static Frontend Dev Server (Proxies /api to backend)
cd frontend
npm run dev
```

---

### Running via Docker Compose
To launch the complete containerized stack (Nginx, PHP-FPM 8.3, MySQL 8, Redis, Mailpit, Queue Worker, and Scheduler):
```bash
docker compose up -d
```
- Web Storefront: [http://localhost](http://localhost)
- Backend API: [http://localhost/api/v1](http://localhost/api/v1)
- Mailpit UI (Email testing): [http://localhost:8025](http://localhost:8025)

---

## 🧪 Testing & Verification

The project includes exhaustive automated test suites covering Unit, Integration, Feature, and Security scenarios.

### Backend PHPUnit Tests
Runs all 167 tests against an in-memory SQLite isolated environment:
```bash
cd backend
./vendor/bin/phpunit
```
```
Tests: 167, Assertions: 729, Failures: 0, Errors: 0
```

### Frontend Unit Tests
Executes native Node.js unit tests for API error normalization, token refresh retry loops, safe DOM rendering, and i18n switching:
```bash
cd frontend
npm run test:unit
```
```
✔ API Core Fetch Wrapper (envelope parse, refresh retry, 429 backoff)
✔ Brand Core Module (token reading, dynamic themes)
✔ DOM Core Helpers (XSS-safe text nodes, zero innerHTML)
✔ i18n Core Module (bilingual en/hi switching)
ℹ tests 13 | pass 13 | fail 0
```

---

## 📄 License & Compliance Notice

This software is developed for licensed retail pharmacy operations. Dispensing of Schedule H, H1, and X medications requires verification by a registered pharmacist under the **Drugs and Cosmetics Act, 1940** and **Pharmacy Practice Regulations**. Customer personal and medical data handling adheres to the **Digital Personal Data Protection (DPDP) Act, 2023**.
