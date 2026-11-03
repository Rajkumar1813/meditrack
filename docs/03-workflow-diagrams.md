# 03 — Workflow Diagrams (Mermaid)

> GitHub, VS Code (Mermaid plugin), Antigravity preview — sab me render hote hain.

## 1. System architecture
```mermaid
flowchart LR
  subgraph Clients
    W[frontend/ Customer Web\nHTML + Tailwind + JS]
    A[frontend/ Admin Web\nHTML + Tailwind + JS]
    M[mobile/ Flutter App\nCustomer + Owner mode]
  end
  CDN[(CDN / Object Storage)]
  LB[Load Balancer / Nginx]
  subgraph Backend[Plain PHP API - stateless x N]
    API[REST /api/v1]
    Q[Queue Workers]
    S[Scheduler]
  end
  DB[(MySQL Primary)]
  RR[(MySQL Read Replica)]
  R[(Redis: cache, queue, rate-limit)]
  PG[Razorpay]
  SMS[MSG91 SMS]
  FCM[Firebase FCM]
  W & A & M --> LB --> API
  W & A & M -.images/rx.-> CDN
  API --> DB
  API --> RR
  API --> R
  API --> Q
  Q --> SMS & FCM
  API <--> PG
  PG -- webhook --> API
  S --> Q
  BR[brand/brand.json] -. sync.mjs .-> W & A & M
```

## 2. OTP login
```mermaid
sequenceDiagram
  participant U as User
  participant C as App/Web
  participant API
  participant R as Redis
  participant SMS as SMS/Email
  U->>C: Phone/Email enter
  C->>API: POST /auth/otp/request
  API->>R: rate-limit check (phone+IP)
  alt limit exceeded
    API-->>C: 429 Retry-After
  else ok
    API->>API: generate 6-digit, store hash (5 min)
    API->>SMS: queue send
    API-->>C: 200 (resend after 60s)
  end
  U->>C: OTP enter
  C->>API: POST /auth/otp/verify
  alt wrong (attempts<5)
    API-->>C: 422 invalid
  else 5 wrong
    API-->>C: 423 locked 15 min
  else correct
    API-->>C: token + role
    C->>C: role=customer → Store | admin/pharmacist → Owner dashboard
  end
```

## 3. Order lifecycle
```mermaid
stateDiagram-v2
  [*] --> PENDING: order placed + stock reserved
  PENDING --> CONFIRMED: payment ok / COD accepted\n(+ Rx approved if needed)
  CONFIRMED --> PACKED: pharmacist packs
  PACKED --> SHIPPED: courier + tracking id
  SHIPPED --> DELIVERED: delivered (COD → paid)
  PENDING --> CANCELLED
  CONFIRMED --> CANCELLED
  PACKED --> CANCELLED
  CANCELLED --> [*]
  DELIVERED --> [*]
```

## 4. Checkout + payment
```mermaid
flowchart TD
  A[Cart] --> B{Rx medicine?}
  B -- Yes --> C[Upload prescription]
  B -- No --> D[Select address]
  C --> D --> E[Choose payment]
  E --> F{Method}
  F -- COD --> G[POST /orders  method=cod]
  F -- UPI/Card/NetBanking --> H[POST /orders → payments/create]
  H --> I[Razorpay checkout]
  I --> J[payments/verify + webhook]
  J -- success --> K[payment_status=paid]
  J -- fail --> L[Retry / cancel + stock release 15 min]
  G --> M[Order PENDING]
  K --> M
  M --> N{Rx pending?}
  N -- Yes --> O[Pharmacist review queue]
  N -- No --> P[Admin/auto confirm]
  O -- approved --> P
  O -- rejected --> Q[Cancel + refund + notify]
  P --> R[CONFIRMED → invoice PDF generate → push/SMS]
```

## 5. Prescription verification
```mermaid
sequenceDiagram
  participant Cu as Customer
  participant API
  participant St as Private Storage
  participant Ph as Pharmacist
  Cu->>API: POST /prescriptions (file)
  API->>St: store (random name, private)
  API-->>Cu: status=pending
  API->>Ph: push "New Rx"
  Ph->>API: GET /admin/prescriptions (signed URL)
  Ph->>API: PATCH review {approve | reject+reason, items}
  API->>Cu: push + SMS
  API->>API: order.rx_status update → order can confirm
```

## 6. Stock (FEFO) + expiry alerts
```mermaid
flowchart TD
  O[Order item qty N] --> L[Lock batches FOR UPDATE\nexp_date ASC, qty>0, exp>today]
  L --> S{Total available >= N?}
  S -- No --> E[422 insufficient stock]
  S -- Yes --> A[Allocate across batches\nsave order_item_batches]
  A --> D[Decrement atomically]
  D --> T{qty <= threshold?}
  T -- Yes --> LS[Low-stock alert → owner push]
  Cron[Daily 08:00 scheduler] --> X[Find batches exp<=30d / <=90d / expired]
  X --> AL[Alerts + push + dashboard]
```

## 7. Refill reminder
```mermaid
flowchart LR
  D[Order DELIVERED] --> R[Create refill_reminder\nremind_at = delivered + course_days - 3]
  R --> C[Scheduler hourly]
  C --> N{due & not dismissed?}
  N -- Yes --> P[Push: 'Refill your medicine' → deep link reorder]
  P --> Re[1-tap reorder → cart]
```

## 8. Brand change flow
```mermaid
flowchart LR
  E[Edit brand/brand.json + replace logo files] --> S[node brand/sync.mjs]
  S --> T1[frontend: tailwind.brand.cjs + brand.css + brand.generated.js]
  S --> T2[backend: config/brand.php]
  S --> T3[brand_tokens.dart + strings.xml + launcher icons yaml]
  T1 --> W[cd frontend && npm run build → Web updated]
  T3 --> F[flutter build → App updated]
  E --> RT[GET /api/v1/brand]
  RT --> AppRT[App runtime refresh: naam/logo/color without release]
```
