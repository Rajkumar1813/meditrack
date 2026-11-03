# 05 — Branding: ek jagah se naam / logo / color change

## Rule
Code me kahin bhi "MediTrack", `#059669`, `emerald-600` ya logo path **hardcode nahi** hoga. Sab `brand/brand.json` se aata hai.

## Kaise badlein
1. `brand/brand.json` me naam / colors / tagline edit karo.
2. Logo files `brand/assets/` me replace karo (same filename): `logo.svg`, `logo-dark.svg`, `logo-mark.svg`, `favicon.png`, `app-icon-1024.png`. Adaptive icon ke liye `logo-mark.png` (512px, transparent) bhi rakho.
3. Run: `node brand/sync.mjs`
4. Web: `cd frontend && npm run build` · App: `dart run flutter_launcher_icons -f flutter_launcher_icons.yaml` phir `flutter build apk`.

## Generated files (haath se edit mat karo)
| Output | Kis liye |
|---|---|
| `frontend/tailwind.brand.cjs` | Tailwind colors (`bg-primary-600`, `shadow-lift`, `rounded-card`) |
| `frontend/src/css/brand.css` | CSS variables + dark mode (3D shadows, tilt) |
| `frontend/src/js/brand.generated.js` | `window.BRAND` (naam, tagline, logo paths) — runtime `/brand` se override |
| `backend/config/brand.php` | Email templates, invoice PDF header, `/api/v1/brand` response (`brand('app.name')`) |
| `mobile/lib/core/brand/brand_tokens.dart` | Flutter compile-time colors/naam |
| `mobile/android/.../values/strings.xml` | Android launcher app name |
| `mobile/flutter_launcher_icons.yaml` | Launcher icon generation |

## Runtime update (bina app release ke)
`GET /api/v1/brand` → naam, tagline, colors, logo URLs, `version`, ETag.
Flutter `BrandController`: app start pe cached brand load → background me `/brand` call (If-None-Match) → version badla to theme rebuild. **Note:** Android launcher icon aur launcher naam runtime me nahi badal sakte (OS limitation) — uske liye naya build chahiye. In-app header/logo/colors runtime me badal jaate hain.

## Tailwind use
```js
// frontend/tailwind.config.js
module.exports = {
  presets: [require('./tailwind.brand.cjs')],
  darkMode: 'class',
  content: ['./src/**/*.html', './src/js/**/*.js'],
};
```
HTML: `<span data-brand="name"></span>` — `core/brand.js` page load pe `window.BRAND` (aur phir `/api/v1/brand`) se bharta hai; logo `<img data-brand-logo>` dark mode me `fullDark` dikhata hai. `<title>` aur favicon `build.mjs` build time pe `brand.generated.js` se inject karta hai. `backend` me: `brand('app.name')` (`config/brand.php` se dot-notation).

## Flutter use
```dart
final c = BrandTokens.primary[600]!;   // ya BrandController.of(context).primary
Text(BrandTokens.appName)
```

## CI guard
`node brand/sync.mjs --check` — agar kisi ne brand.json badla par sync nahi chalaya to build fail.

## Build note
`frontend/build.mjs` `brand/assets/*` ko `dist/assets/brand/` me copy karta hai (logo/favicon). Isliye logo replace karke sirf `node brand/sync.mjs && cd frontend && npm run build` kaafi hai.
