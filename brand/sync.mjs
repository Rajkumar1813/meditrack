#!/usr/bin/env node
/**
 * MediTrack brand sync — brand/brand.json se web + backend + Flutter ke files generate karta hai.
 *   node brand/sync.mjs           → generate (frontend/, backend/, mobile/ teeno ke liye)
 *   node brand/sync.mjs --check   → CI: outdated ho to exit 1
 * Generated files ko haath se edit mat karo.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CHECK = process.argv.includes('--check');
const b = JSON.parse(fs.readFileSync(path.join(ROOT, 'brand/brand.json'), 'utf8'));
const HEADER = 'AUTO-GENERATED from brand/brand.json by brand/sync.mjs — DO NOT EDIT';

const out = {}; // relativePath -> content
const hexToDart = (h) => '0xFF' + h.replace('#', '').toUpperCase();
const xml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/'/g, "\\'");

// 1) Tailwind preset
out['frontend/tailwind.brand.cjs'] = `/* ${HEADER} */
module.exports = {
  theme: {
    extend: {
      colors: {
        primary: ${JSON.stringify(b.colors.primary, null, 8).replace(/\n {6}\}$/, '\n      }')},
        accent: '${b.colors.accent}',
        success: '${b.colors.success}',
        warning: '${b.colors.warning}',
        danger: '${b.colors.danger}',
        info: '${b.colors.info}',
        surface: { light: '${b.colors.light.surface}', dark: '${b.colors.dark.surface}' },
        canvas:  { light: '${b.colors.light.background}', dark: '${b.colors.dark.background}' },
      },
      borderRadius: { card: '${b.shape.radiusCard}px', btn: '${b.shape.radiusButton}px', input: '${b.shape.radiusInput}px' },
      fontFamily: { sans: ['${b.fonts.sans}', ${JSON.stringify(b.fonts.fallback)}] },
      boxShadow: { soft: '${b.effects3d.shadowSoft}', lift: '${b.effects3d.shadowLift}' },
    },
  },
};
`;

// 2) CSS variables (web runtime)
const L = b.colors.light, D = b.colors.dark;
out['frontend/src/css/brand.css'] = `/* ${HEADER} */
:root {
${Object.entries(b.colors.primary).map(([k, v]) => `  --brand-primary-${k}: ${v};`).join('\n')}
  --brand-accent: ${b.colors.accent};
  --brand-bg: ${L.background}; --brand-surface: ${L.surface}; --brand-border: ${L.border};
  --brand-text: ${L.text}; --brand-muted: ${L.muted};
  --brand-shadow-soft: ${b.effects3d.shadowSoft};
  --brand-shadow-lift: ${b.effects3d.shadowLift};
  --brand-tilt-max: ${b.effects3d.tiltMaxDeg}deg;
}
.dark {
  --brand-bg: ${D.background}; --brand-surface: ${D.surface}; --brand-border: ${D.border};
  --brand-text: ${D.text}; --brand-muted: ${D.muted};
}
`;

// 2b) Frontend JS fallback (runtime me /api/v1/brand se override hota hai)
out['frontend/src/js/brand.generated.js'] = `/* ${HEADER} */
window.BRAND = ${JSON.stringify({
  version: b.version, name: b.app.name, shortName: b.app.shortName, tagline: b.app.tagline,
  adminTagline: b.app.adminTagline, currencySymbol: b.app.currencySymbol,
  supportEmail: b.app.supportEmail, supportPhone: b.app.supportPhone,
  logo: { full: 'assets/brand/logo.svg', fullDark: 'assets/brand/logo-dark.svg', mark: 'assets/brand/logo-mark.svg', favicon: 'assets/brand/favicon.png' },
  locales: b.app.locales, defaultLocale: b.app.defaultLocale,
}, null, 2)};
`;

// 3) PHP config (plain array, brand('app.name') helper se read hota hai)
const php = (v, i = 1) => {
  const pad = '    '.repeat(i);
  if (Array.isArray(v)) return `[${v.map((x) => php(x, i)).join(', ')}]`;
  if (v && typeof v === 'object')
    return `[\n${Object.entries(v).map(([k, x]) => `${pad}'${k}' => ${php(x, i + 1)},`).join('\n')}\n${'    '.repeat(i - 1)}]`;
  if (typeof v === 'number') return String(v);
  return `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
};
out['backend/config/brand.php'] = `<?php\n// ${HEADER}\nreturn ${php(b)};\n`;

// 4) Flutter tokens
out['mobile/lib/core/brand/brand_tokens.dart'] = `// ${HEADER}
import 'dart:ui';

class BrandTokens {
  static const String version = '${b.version}';
  static const String appName = '${b.app.name}';
  static const String tagline = '${b.app.tagline}';
  static const String adminTagline = '${b.app.adminTagline}';
  static const String currencySymbol = '${b.app.currencySymbol}';
  static const String supportEmail = '${b.app.supportEmail}';
  static const String supportPhone = '${b.app.supportPhone}';

  static const Map<int, Color> primary = {
${Object.entries(b.colors.primary).map(([k, v]) => `    ${k}: Color(${hexToDart(v)}),`).join('\n')}
  };
  static const Color accent = Color(${hexToDart(b.colors.accent)});
  static const Color success = Color(${hexToDart(b.colors.success)});
  static const Color warning = Color(${hexToDart(b.colors.warning)});
  static const Color danger = Color(${hexToDart(b.colors.danger)});
  static const Color info = Color(${hexToDart(b.colors.info)});

  static const Color lightBackground = Color(${hexToDart(L.background)});
  static const Color lightSurface = Color(${hexToDart(L.surface)});
  static const Color lightBorder = Color(${hexToDart(L.border)});
  static const Color lightText = Color(${hexToDart(L.text)});
  static const Color lightMuted = Color(${hexToDart(L.muted)});
  static const Color darkBackground = Color(${hexToDart(D.background)});
  static const Color darkSurface = Color(${hexToDart(D.surface)});
  static const Color darkBorder = Color(${hexToDart(D.border)});
  static const Color darkText = Color(${hexToDart(D.text)});
  static const Color darkMuted = Color(${hexToDart(D.muted)});

  static const double radiusCard = ${b.shape.radiusCard};
  static const double radiusButton = ${b.shape.radiusButton};
  static const double radiusInput = ${b.shape.radiusInput};
  static const double tiltMaxDeg = ${b.effects3d.tiltMaxDeg};
  static const String fontFamily = '${b.fonts.sans}';
}
`;

// 5) Android app name + launcher icon config
out['mobile/android/app/src/main/res/values/strings.xml'] = `<?xml version="1.0" encoding="utf-8"?>
<!-- ${HEADER} -->
<resources>
    <string name="app_name">${xml(b.app.name)}</string>
</resources>
`;
out['mobile/flutter_launcher_icons.yaml'] = `# ${HEADER}
flutter_launcher_icons:
  android: true
  ios: false
  image_path: "../${b.logo.appIcon}"
  adaptive_icon_background: "${b.colors.primary['600']}"
  adaptive_icon_foreground: "../${b.logo.mark.replace(/\.svg$/, '.png')}"
`;

let stale = 0;
for (const [rel, content] of Object.entries(out)) {
  const file = path.join(ROOT, rel);
  const cur = fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : null;
  if (CHECK) { if (cur !== content) { console.error('OUTDATED:', rel); stale++; } continue; }
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, content);
  console.log('wrote', rel);
}
if (CHECK) { if (stale) process.exit(1); console.log('brand files up to date'); }
else console.log(`\nDone. Ab: frontend → cd frontend && npm run build · app → dart run flutter_launcher_icons -f flutter_launcher_icons.yaml && flutter build apk`);
