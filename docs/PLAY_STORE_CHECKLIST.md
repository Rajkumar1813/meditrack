# Google Play Store Release & Medical App Compliance Checklist

This checklist guides the submission of the **MediTrack** Android application (`mobile/`) to the **Google Play Console** in strict adherence with Google Play's **Health & Medical Apps Policy**, **Data Safety Declarations**, and **Indian e-Pharmacy guidelines**.

---

## 1. Android Release Signing & AAB Build

### A. Generate Upload Keystore
Generate an industry-standard RSA 4096-bit release keystore:
```bash
keytool -genkey -v -keystore android-release-key.jks \
  -keyalg RSA -keysize 4096 -validity 10000 \
  -alias meditrack-release-key
```

### B. Configure `key.properties`
Create `mobile/android/key.properties` (never commit this file to git; it is gitignored):
```properties
storePassword=YOUR_SECURE_KEYSTORE_PASSWORD
keyPassword=YOUR_SECURE_KEY_PASSWORD
keyAlias=meditrack-release-key
storeFile=/absolute/path/to/android-release-key.jks
```

### C. Build Signed Android App Bundle (AAB)
```bash
cd mobile
flutter clean
flutter pub get
flutter build appbundle --release
```
The production bundle is generated at:
`mobile/build/app/outputs/bundle/release/app-release.aab`

---

## 2. Google Play Store Declarations

### A. Medical Apps Policy Declaration
- **App Category**: `Medical` (Under Store Settings > Store Category).
- **Core App Functionality**: Online medicine ordering, prescription upload verification by licensed pharmacists, and FEFO course refill reminders.
- **Statutory Healthcare Disclaimers**:
  - The app must state: *"Dispensing of Schedule H, H1, and X medications requires verification by a registered pharmacist under the Drugs and Cosmetics Act, 1940. MediTrack does not facilitate the sale of controlled substances without a verified prescription."*
  - License documentation: Upload state Retail Drug License (Forms 20 & 21) in the Play Console declaration.

### B. Data Safety Form Declarations
Google Play requires transparent disclosure of all collected data types:

| Data Type | Purpose | Encrypted in Transit? | Ephemeral? | User Deletion Option? |
|---|---|---|---|---|
| **Personal Info (Name, Email, Phone, Address)** | App functionality, Order delivery, Account management | Yes (HTTPS / TLS 1.3) | No | Yes (DPDP Account Deletion) |
| **Health Info (Prescriptions, Ordered Medications)** | Prescription review by licensed pharmacist, Order fulfillment | Yes (HTTPS + HMAC signed storage) | No | Yes |
| **Financial Info (Payment Transaction ID)** | Fraud prevention, Razorpay payment verification | Yes (HTTPS) | No | No (Retained for GST tax audit) |
| **Device Identifiers (FCM Push Token)** | Push notifications (order tracking, refill alerts) | Yes (HTTPS) | No | Yes (Revoked on logout / deletion) |

### C. DPDP Act 2023 & Account Deletion URL
- In the Play Console **App Content > Data Safety > Delete Account URL**, provide:
  `https://meditrack.example.com/delete-account`
- This page routes authenticated users to the in-app deletion request endpoint:
  `POST /api/v1/profile/delete-account-request`

---

## 3. App Icons & Splash Screen Assets

- **Launcher Icons**:
  Configured in `mobile/flutter_launcher_icons.yaml` with adaptive background and foreground vector:
  ```yaml
  flutter_launcher_icons:
    android: true
    ios: true
    image_path: "assets/icon/app_icon.png"
    adaptive_icon_background: "#059669"
    adaptive_icon_foreground: "assets/icon/app_icon_foreground.png"
  ```
- **Splash Screen**:
  Android 12+ SplashScreen API configured with `@drawable/launch_background` using brand primary color `#059669`.

---

## 4. Pre-Launch Testing Checklist

- [ ] Complete end-to-end checkout flow tested with release build.
- [ ] Push notifications received in background and terminated states.
- [ ] Camera and Gallery runtime permissions tested on Android 13, 14, and 15 (Granular media permissions `READ_MEDIA_IMAGES`).
- [ ] Target SDK set to latest Android API (API 34/35).
- [ ] Privacy policy link active and accessible without requiring login.

