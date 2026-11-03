# MediTrack — Statutory & Regulatory Compliance Documentation

This document outlines MediTrack's compliance with Indian pharmaceutical laws (**Drugs and Cosmetics Act, 1940**, **Pharmacy Practice Regulations, 2015**) and privacy law (**Digital Personal Data Protection (DPDP) Act, 2023**).

---

## 1. Drugs and Cosmetics Act, 1940 & Pharmacy Rules

### A. Implemented in Codebase

| Regulatory Requirement | Technical Implementation | File Reference |
|---|---|---|
| **Prescription Enforcement (Schedule H, H1, X)** | All medications categorized under Schedule H, H1, or X enforce `requires_rx = 1`. Cart checkout is blocked unless a valid prescription is attached. | [`CheckoutScreen`](file:///c:/xampp/htdocs/Meditrack/mobile/lib/features/checkout/presentation/checkout_screen.dart), [`OrderService.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Services/OrderService.php) |
| **Pharmacist Stamp & Verification** | Orders containing Rx medications cannot transition to `confirmed` or `packed` until an authorized, registered pharmacist approves the prescription. The review records `reviewed_by`, `reviewed_at`, and the pharmacist's state council registration number. | [`PrescriptionController.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Controllers/Api/V1/Admin/PrescriptionController.php), [`OwnershipPolicy.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Policies/OwnershipPolicy.php) |
| **Batch Number & Expiry on Tax Invoices** | Under Rule 65, every retail invoice must state the manufacturer, batch number, and expiry date. The Dompdf invoice generator pulls exact FEFO batch allocation records and prints them per line item. | [`InvoiceService.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Services/InvoiceService.php), [`pdf.php`](file:///c:/xampp/htdocs/Meditrack/backend/templates/invoices/pdf.php) |
| **Drug License & GSTIN Disclosure** | The pharmacy's Retail Drug License number (Form 20/21) and GSTIN are dynamically stamped onto the invoice header, footer, web storefront, and app. | [`brand.json`](file:///c:/xampp/htdocs/Meditrack/brand/brand.json), [`SettingController.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Controllers/Api/V1/Admin/SettingController.php) |
| **FEFO & Expired Drug Quarantine** | The automated FEFO engine selects oldest unexpired batches first (`exp_date > CURRENT_DATE`). Batches within 30 days are flagged in the admin monitor, and expired batches cannot be reserved or sold. | [`BatchRepository.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Repositories/BatchRepository.php), [`ExpiryController.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Controllers/Api/V1/Admin/ExpiryController.php) |
| **Invoice & Sale Register Retention** | Indian pharmacy law mandates retail drug records and invoices be preserved for a minimum of 2 years. Invoices and orders are immutable and retained with gap-free sequence numbers (`INV-YYYY-XXXXXX`). | [`InvoiceRepository.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Repositories/InvoiceRepository.php) |

### B. Pharmacy Owner & Legal Team Action Items (Off-Codebase Responsibilities)

The pharmacy license holder must maintain the following operational responsibilities:
- [ ] **Physical Drug Licenses**: Ensure valid Form 20 (Allopathic drugs) and Form 21 (Schedule C and C(1) drugs) licenses issued by the State Drug Controller are displayed inside the licensed premises.
- [ ] **Registered Pharmacist Physical Presence**: A registered pharmacist registered with the State Pharmacy Council must oversee dispensing and compounding at the physical warehouse/dispensary.
- [ ] **Schedule X & H1 Separate Registers**: Physical registers must be maintained for Schedule X and specific Schedule H1 antibiotics (e.g. Third/Fourth generation cephalosporins, fluoroquinolones) with doctor's registration number and 2-year retention.
- [ ] **Cold Chain Maintenance**: Temperature logs must be maintained for refrigerated biologicals/vaccines ($2^\circ\text{C}$ to $8^\circ\text{C}$).
- [ ] **Biomedical Waste / Expired Drug Disposal**: Expired stock logged in `batches` must be disposed of in coordination with State Pollution Control Board authorized waste handlers, with signed destruction certificates on file.

---

## 2. Digital Personal Data Protection (DPDP) Act, 2023

### A. Implemented in Codebase

| DPDP Principle | Technical Implementation | Code Reference |
|---|---|---|
| **Right to Erasure (Account Deletion)** | Users can submit account deletion requests via web and mobile (`POST /api/v1/profile/delete-account-request`). Admins review and process requests with irreversible anonymization of PII, while retaining required financial records for tax/statutory audit. | [`CustomerController.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Controllers/Api/V1/Admin/CustomerController.php), [`002_invoices_and_dpdp.sql`](file:///c:/xampp/htdocs/Meditrack/backend/database/migrations/002_invoices_and_dpdp.sql) |
| **Right to Data Portability (Data Export)** | Customers and admins can trigger full personal data export in structured JSON format (`GET /api/v1/admin/customers/{id}/export`), extracting addresses, orders, and prescription metadata. | [`CustomerController.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Controllers/Api/V1/Admin/CustomerController.php) |
| **Data Minimization & Confidentiality** | Prescriptions and medical records are stored in a private directory outside the web root (`backend/storage/prescriptions/`). Direct public access is disabled; downloads require short-lived (5 min) HMAC signed URLs verified against the customer or assigned pharmacist. | [`PrescriptionService.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Services/PrescriptionService.php), [`OwnershipPolicy.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Policies/OwnershipPolicy.php) |
| **Audit Trails & Non-Repudiation** | All sensitive status transitions, inventory changes, price updates, and customer block/unblock actions are recorded in the `audit_logs` table with actor ID, IP address, and timestamp. | [`AuditLogRepository.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Repositories/AuditLogRepository.php) |
| **Authentication Security** | OTP codes are stored strictly as bcrypt hashes (`code_hash`). Plain tokens are never written to database. Web refresh tokens use `HttpOnly`, `SameSite=Strict` cookies with CSRF `OriginCheck` middleware. Mobile refresh tokens are stored in Android Keystore / iOS Keychain (`FlutterSecureStorage`). | [`AuthService.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Services/AuthService.php), [`OriginCheck.php`](file:///c:/xampp/htdocs/Meditrack/backend/src/Middleware/OriginCheck.php) |

### B. Legal & Privacy Notice Checklist

- [ ] **Privacy Notice**: Publish a clear Privacy Policy detailing:
  - Identity and contact information of the Data Protection Officer (DPO).
  - Purpose of data processing (delivery, compliance, invoicing).
  - Data retention duration (health records: 2 years under Pharmacy Act; financial records: 6 years under GST Act).
- [ ] **Grievance Redressal Mechanism**: Provide customer support email (`support@meditrack.example.com`) and DPO contact in the app footer.
- [ ] **Breach Notification Protocol**: Security incident response plan configured to notify the Data Protection Board of India within 72 hours of any confirmed security breach.

