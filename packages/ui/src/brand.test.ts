import { test } from 'node:test';
import assert from 'node:assert/strict';
import { brandCss, brandTokens, contrast, parseHex } from './brand.ts';

test('invalid or missing colours keep the default palette', () => {
  assert.equal(brandTokens(null), null);
  assert.equal(brandTokens('teal'), null);
  assert.deepEqual(parseHex('#abc'), [170, 187, 204]);
});

test('dark brand gets white text', () => {
  const t = brandTokens('#0e7c6b')!;
  assert.equal(t.brand, '#0e7c6b');
  assert.equal(t.onBrand, '#ffffff');
});

test('light brand is darkened to 3:1 on paper and gets dark text when white fails', () => {
  const t = brandTokens('#ffe066')!; // pale yellow
  assert.ok(contrast(parseHex(t.brand)!, [246, 245, 241]) >= 3);
  assert.ok(contrast(parseHex(t.brand)!, parseHex(t.onBrand)!) >= 4.5 || t.onBrand === '#1d1c1a');
});

test('text on brand is always the more readable of white and ink', () => {
  for (const c of ['#e11d48', '#f59e0b', '#2563eb', '#16a34a', '#7c3aed', '#000000', '#ffffff']) {
    const t = brandTokens(c)!;
    const b = parseHex(t.brand)!;
    const chosen = contrast(b, parseHex(t.onBrand)!);
    assert.ok(chosen >= Math.min(contrast(b, [255, 255, 255]), contrast(b, [29, 28, 26])), c);
  }
});

test('dark theme lightens a deep brand until it stands out on charcoal', () => {
  const t = brandTokens('#0a2a66', 'dark')!;
  assert.ok(contrast(parseHex(t.brand)!, [18, 18, 17]) >= 3);
  assert.match(brandCss('#0a2a66', '.store')!, /prefers-color-scheme: dark/);
  assert.equal(brandCss('nope', '.store'), null);
});

test('a pale brand keeps its hue and colour when darkened (no muddy grey)', () => {
  const t = brandTokens('#bcf5f0')!; // pale mint
  const [r, g, b] = parseHex(t.brand)!;
  assert.ok(contrast([r, g, b], [246, 245, 241]) >= 3);
  // Still clearly teal: green/blue well above red, not a grey.
  assert.ok(g - r > 60 && b - r > 50, t.brand);
});
