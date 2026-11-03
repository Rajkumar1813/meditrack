import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const SRC_DIR = path.join(__dirname, 'src');
const DIST_DIR = path.join(__dirname, 'dist');
const BRAND_JSON_PATH = path.join(__dirname, '..', 'brand', 'brand.json');
const BRAND_ASSETS_SRC = path.join(__dirname, '..', 'brand', 'assets');
const BRAND_ASSETS_DEST = path.join(DIST_DIR, 'assets', 'brand');

console.log('=== MediTrack Static Site Builder ===');

// 1. Read Brand Configuration
const brandConfig = JSON.parse(fs.readFileSync(BRAND_JSON_PATH, 'utf8'));
const appName = brandConfig.app?.name || 'MediTrack';
const faviconPath = '/assets/brand/favicon.png';

// 2. Prepare Dist Directory
if (fs.existsSync(DIST_DIR)) {
  fs.rmSync(DIST_DIR, { recursive: true, force: true });
}
fs.mkdirSync(DIST_DIR, { recursive: true });

// 3. Copy Brand Assets
console.log('Copying brand assets...');
fs.mkdirSync(BRAND_ASSETS_DEST, { recursive: true });
if (fs.existsSync(BRAND_ASSETS_SRC)) {
  const assets = fs.readdirSync(BRAND_ASSETS_SRC);
  for (const asset of assets) {
    fs.copyFileSync(path.join(BRAND_ASSETS_SRC, asset), path.join(BRAND_ASSETS_DEST, asset));
  }
}

// 4. Copy Languages Directory
console.log('Copying language dictionaries...');
const LANG_SRC = path.join(SRC_DIR, 'lang');
const LANG_DEST = path.join(DIST_DIR, 'lang');
if (fs.existsSync(LANG_SRC)) {
  fs.mkdirSync(LANG_DEST, { recursive: true });
  for (const file of fs.readdirSync(LANG_SRC)) {
    fs.copyFileSync(path.join(LANG_SRC, file), path.join(LANG_DEST, file));
  }
}

// 5. Copy JavaScript Assets
console.log('Copying JavaScript modules...');
function copyRecursive(src, dest) {
  if (!fs.existsSync(src)) return;
  fs.mkdirSync(dest, { recursive: true });
  for (const item of fs.readdirSync(src)) {
    const s = path.join(src, item);
    const d = path.join(dest, item);
    if (fs.statSync(s).isDirectory()) {
      copyRecursive(s, d);
    } else {
      fs.copyFileSync(s, d);
    }
  }
}
copyRecursive(path.join(SRC_DIR, 'js'), path.join(DIST_DIR, 'js'));

// 6. Process HTML with Partials and Brand Tokens
console.log('Processing HTML templates and partials...');

function resolveIncludes(content, baseDir) {
  const includeRegex = /<!--\s*@include\s+([^\s]+)\s*-->/g;
  return content.replace(includeRegex, (match, includePath) => {
    const fullPath = path.join(SRC_DIR, includePath.trim());
    if (fs.existsSync(fullPath)) {
      let partialContent = fs.readFileSync(fullPath, 'utf8');
      // Recursively resolve nested partials
      return resolveIncludes(partialContent, path.dirname(fullPath));
    } else {
      console.warn(`Warning: Partial not found: ${includePath}`);
      return `<!-- Missing partial: ${includePath} -->`;
    }
  });
}

function processHtmlFile(filePath, relPath) {
  let content = fs.readFileSync(filePath, 'utf8');

  // Resolve includes
  content = resolveIncludes(content, path.dirname(filePath));

  // Injections
  const pageTitle = path.basename(filePath, '.html');
  const formattedTitle = pageTitle === 'index' ? 'Home' : pageTitle.charAt(0).toUpperCase() + pageTitle.slice(1);

  content = content.replace(/<!--\s*@title\s*-->/g, formattedTitle);
  content = content.replace(/<!--\s*@favicon\s*-->/g, faviconPath);

  const destPath = path.join(DIST_DIR, relPath);
  fs.mkdirSync(path.dirname(destPath), { recursive: true });
  fs.writeFileSync(destPath, content, 'utf8');
}

function scanHtml(dir, base = '') {
  for (const item of fs.readdirSync(dir)) {
    const full = path.join(dir, item);
    const rel = path.join(base, item);
    if (fs.statSync(full).isDirectory()) {
      if (item !== 'partials') {
        scanHtml(full, rel);
      }
    } else if (item.endsWith('.html')) {
      processHtmlFile(full, rel);
    }
  }
}
scanHtml(SRC_DIR);

// 7. Compile Tailwind CSS
console.log('Compiling Tailwind CSS with brand tokens...');
fs.mkdirSync(path.join(DIST_DIR, 'css'), { recursive: true });
try {
  const tailwindCmd = 'npx tailwindcss -i ./src/css/app.css -o ./dist/css/app.css --minify';
  execSync(tailwindCmd, { cwd: __dirname, stdio: 'inherit' });
  console.log('Tailwind CSS compiled successfully.');
} catch (err) {
  console.error('Tailwind build error:', err.message);
  process.exit(1);
}

// Copy .htaccess if present
if (fs.existsSync(path.join(SRC_DIR, '.htaccess'))) {
  fs.copyFileSync(path.join(SRC_DIR, '.htaccess'), path.join(DIST_DIR, '.htaccess'));
}

console.log('Build completed successfully in dist/');

