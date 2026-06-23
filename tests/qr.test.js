/* Strukturtest für den QR-Encoder (qr.js).
 * Prüft Finder-/Timing-/Alignment-Muster, Versionswahl und alle EC-Stufen.
 * Aufruf:  node tests/qr.test.js
 */
'use strict';
global.window = global;
require('../qr.js');

let fails = 0;
function check(cond, msg) { if (!cond) { console.log('  FAIL:', msg); fails++; } }

function finderOK(m, oy, ox) {
  for (let y = 0; y < 7; y++) for (let x = 0; x < 7; x++) {
    const d = Math.max(Math.abs(x - 3), Math.abs(y - 3));
    if (m[oy + y][ox + x] !== (d !== 2 && d !== 4)) return false;
  }
  return true;
}

const urls = [
  'https://example.com/x',
  'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
  'https://example.com/' + 'a'.repeat(120),
];

['L', 'M', 'Q', 'H'].forEach(function (ecl) {
  urls.forEach(function (u) {
    let qr;
    try { qr = QRCodeGen.encode(u, ecl); }
    catch (e) { check(false, ecl + ' len=' + u.length + ' -> ' + e.message); return; }
    const s = qr.size, m = qr.modules;
    check(s === 4 * qr.version + 17, ecl + ' Größe inkonsistent');
    check(finderOK(m, 0, 0) && finderOK(m, 0, s - 7) && finderOK(m, s - 7, 0),
      ecl + ' len=' + u.length + ' Finder-Muster fehlerhaft');
    let timing = true;
    for (let i = 8; i < s - 8; i++) if (m[6][i] !== (i % 2 === 0)) timing = false;
    check(timing, ecl + ' Timing-Muster fehlerhaft');
  });
});

// Alignment-Muster bei Version 3 (Zentrum 22,22)
const v3 = QRCodeGen.encode('https://example.com/' + 'a'.repeat(20), 'M');
if (v3.version === 3) {
  const m = v3.modules; let al = true;
  for (let y = -2; y <= 2; y++) for (let x = -2; x <= 2; x++) {
    const d = Math.max(Math.abs(x), Math.abs(y));
    if (m[22 + y][22 + x] !== (d !== 1)) al = false;
  }
  check(al, 'Alignment-Muster v3 fehlerhaft');
}

if (fails === 0) { console.log('QR-Tests: alle bestanden ✓'); process.exit(0); }
else { console.log('QR-Tests: ' + fails + ' Fehler'); process.exit(1); }
