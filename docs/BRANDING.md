# MediTrack — Brand Customization & Re-Branding Guide

MediTrack is engineered from the ground up around a **Single Source of Truth** for brand identity. You can completely white-label or re-brand the platform (name, logos, primary/secondary colors, legal licenses, contact information, and themes) in seconds by modifying a single JSON file.

---

## 1. The Single Source of Truth: `brand/brand.json`

All brand metadata is stored in [`brand/brand.json`](../brand/brand.json):

```json
{
  "name": "MediTrack",
  "legal_name": "MediTrack Pharmacy Private Limited",
  "tagline": "Your Trusted Healthcare Partner",
  "admin_tagline": "Pharmacy Inventory Operating System",
  "support_email": "support@meditrack.example.com",
  "support_phone": "+91-11-23456789",
  "drug_license_no": "DL-2026-DEL-104928",
  "gstin": "07AAAAA0000A1Z5",
  "pharmacist_reg_no": "DEL-PH-2026-99124",
  "colors": {
    "primary": "#059669",
    "primary_dark": "#047857",
    "primary_light": "#10b981",
    "accent": "#f59e0b",
    "danger": "#dc2626",
    "warning": "#ea580c",
    "info": "#2563eb",
    "success": "#059669"
  },
  "typography": {
    "font_family": "Inter, sans-serif"
  },
  "shapes": {
    "radius_sm": "8px",
    "radius_md": "12px",
    "radius_lg": "16px",
    "radius_full": "9999px"
  }
}
```

---

## 2. Propagating Changes Across All Platforms

Whenever you edit `brand/brand.json` or update brand asset files in `brand/assets/` (`logo.svg`, `favicon.svg`), execute the brand synchronization compiler:

```bash
node brand/sync.mjs
```

### What `sync.mjs` Automatically Does:
1. **Backend Propagation**:
   - Generates `backend/config/brand.php`.
   - Accessible in any PHP controller, repository, or email template via `brand('name')`, `brand('colors.primary')`, `brand('gstin')`, etc.
   - Updates Dompdf GST tax invoice header with new legal details.

2. **Frontend Web Propagation**:
   - Compiles brand tokens into `frontend/tailwind.brand.cjs`.
   - Injects brand tokens into Tailwind CSS config.
   - Copies SVG logos and favicons into `frontend/src/assets/brand/`.
   - Updates page titles, meta tags, and open-graph tags across HTML files.

3. **Flutter Mobile Propagation**:
   - Generates `mobile/lib/core/brand/brand_tokens.dart` with matching `Color` swatches and shape radii.
   - Generates `mobile/android/app/src/main/res/values/strings.xml` with the new application name.
   - Re-generates Android launcher icons via `flutter_launcher_icons`.

---

## 3. Re-building Frontend & Mobile After Brand Sync

```bash
# 1. Rebuild Frontend bundle
cd frontend
npm run build

# 2. Re-analyze & test mobile client
cd ../mobile
flutter clean
flutter pub get
flutter run
```

---

## 4. Verification Checklist

- [ ] Run `node brand/sync.mjs --check` to verify all generated artifacts match `brand.json`.
- [ ] Check `GET /api/v1/brand` returns the updated brand payload and a fresh `ETag`.
- [ ] Verify invoices downloaded via `GET /api/v1/orders/{id}/invoice` reflect the new shop name, GSTIN, and Drug License number.
- [ ] Check that web and mobile headers display the new color palette and logo.

