// Copies the self-hosted fonts, KaTeX and the Lucide icon sprite from node_modules
// into public/assets so the app runs offline under plain Apache, with no Node at runtime.
// Run once after `npm install`:  npm run assets
import { copyFileSync, mkdirSync, readFileSync, writeFileSync, readdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const nm = join(root, 'node_modules');
const out = join(root, 'public', 'assets');

function copy(from, to) {
  mkdirSync(dirname(to), { recursive: true });
  copyFileSync(from, to);
}

// Fonts (latin + latin-ext subsets cover English and Filipino, including ñ).
const fonts = [
  ['@fontsource-variable/besley/files', 'besley-latin-wght-normal.woff2'],
  ['@fontsource-variable/besley/files', 'besley-latin-ext-wght-normal.woff2'],
  ['@fontsource-variable/besley/files', 'besley-latin-wght-italic.woff2'],
  ['@fontsource-variable/atkinson-hyperlegible-next/files', 'atkinson-hyperlegible-next-latin-wght-normal.woff2'],
  ['@fontsource-variable/atkinson-hyperlegible-next/files', 'atkinson-hyperlegible-next-latin-ext-wght-normal.woff2'],
  ['@fontsource-variable/atkinson-hyperlegible-next/files', 'atkinson-hyperlegible-next-latin-wght-italic.woff2'],
  ['@fontsource/atkinson-hyperlegible-mono/files', 'atkinson-hyperlegible-mono-latin-500-normal.woff2'],
];
for (const [dir, file] of fonts) copy(join(nm, dir, file), join(out, 'fonts', file));

// KaTeX for readable maths in companion answers and lesson content.
const katex = join(nm, 'katex', 'dist');
copy(join(katex, 'katex.min.css'), join(out, 'vendor', 'katex', 'katex.min.css'));
copy(join(katex, 'katex.min.js'), join(out, 'vendor', 'katex', 'katex.min.js'));
copy(join(katex, 'contrib', 'auto-render.min.js'), join(out, 'vendor', 'katex', 'auto-render.min.js'));
for (const f of readdirSync(join(katex, 'fonts')).filter((f) => f.endsWith('.woff2'))) {
  copy(join(katex, 'fonts', f), join(out, 'vendor', 'katex', 'fonts', f));
}

// Lucide icons used by the interface, combined into one SVG sprite.
const icons = [
  'house', 'book-open', 'notebook-pen', 'clipboard-list', 'clipboard-check', 'list-checks', 'message-circle',
  'messages-square', 'sprout', 'leaf', 'chart-line', 'chart-column', 'calendar', 'calendar-days', 'calendar-plus',
  'settings', 'circle-help', 'bell', 'search', 'user', 'users', 'user-plus', 'user-x', 'user-check', 'log-out',
  'menu', 'x', 'plus', 'pencil', 'trash-2', 'archive', 'archive-restore', 'upload', 'download', 'file-text', 'file',
  'file-up', 'link', 'external-link', 'eye', 'eye-off', 'lock', 'shield-check', 'scroll-text', 'school',
  'graduation-cap', 'layers', 'folder', 'folder-open', 'tag', 'funnel', 'arrow-up-down', 'arrow-up', 'arrow-down',
  'chevron-down', 'chevron-right', 'chevron-left', 'chevron-up', 'arrow-left', 'arrow-right', 'check', 'circle-check',
  'triangle-alert', 'circle-alert', 'info', 'clock', 'timer', 'refresh-cw', 'rotate-ccw', 'send', 'square',
  'copy', 'flag', 'share-2', 'lightbulb', 'list-ordered', 'quote', 'key-round', 'mail', 'gauge', 'activity',
  'bookmark', 'ellipsis', 'megaphone', 'inbox', 'history', 'languages', 'pen-line', 'square-pen', 'circle-dot',
  'play', 'panel-right', 'sliders-horizontal', 'database', 'plug', 'file-spreadsheet', 'table', 'target',
  'book-marked', 'layout-dashboard', 'hand-helping', 'repeat', 'shuffle', 'circle-x', 'list', 'type', 'pause',
  'chevrons-up-down', 'move-up', 'move-down', 'paperclip', 'save', 'send-horizontal', 'undo-2', 'wifi-off',
  'printer', 'scissors', 'book-user',
];
const missing = [];
let sprite = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none">\n';
for (const name of icons) {
  const file = join(nm, 'lucide-static', 'icons', `${name}.svg`);
  if (!existsSync(file)) { missing.push(name); continue; }
  const svg = readFileSync(file, 'utf8');
  const inner = svg.replace(/<!--[\s\S]*?-->/g, '').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').trim();
  sprite += `<symbol id="i-${name}" viewBox="0 0 24 24">${inner.replace(/\s*\n\s*/g, '')}</symbol>\n`;
}
sprite += '</svg>\n';
writeFileSync(join(out, 'icons', 'sprite.svg'), sprite);

console.log(`Copied ${fonts.length} fonts, KaTeX, and ${icons.length - missing.length} icons.`);
if (missing.length) {
  console.error(`Missing Lucide icons: ${missing.join(', ')}`);
  process.exitCode = 1;
}
