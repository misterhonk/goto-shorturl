/* Eigenständiger QR-Code-Generator (Byte-Modus, UTF-8).
 * Implementiert nach der QR-Spezifikation (Algorithmus angelehnt an
 * Project Nayuki, MIT). Erzeugt eine Modul-Matrix für beliebigen Text.
 *
 *   QRCodeGen.encode(text, "L"|"M"|"Q"|"H")  ->  { size, modules:[ [bool] ] }
 */
(function (global) {
  'use strict';

  var ECL = { L: { ord: 0, fmt: 1 }, M: { ord: 1, fmt: 0 }, Q: { ord: 2, fmt: 3 }, H: { ord: 3, fmt: 2 } };
  var FMT = [1, 0, 3, 2]; // Format-Bits je EC-Ordinal (L,M,Q,H)

  var ECC_CW = [
    [-1,7,10,15,20,26,18,20,24,30,18,20,24,26,30,22,24,28,30,28,28,28,28,30,30,26,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
    [-1,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26,26,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28],
    [-1,13,22,18,26,18,24,18,22,20,24,28,26,24,20,30,24,28,28,26,30,28,30,30,30,30,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],
    [-1,17,28,22,16,22,28,26,26,24,28,24,28,22,24,24,30,28,28,26,28,30,24,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30]
  ];
  var NUM_BLOCKS = [
    [-1,1,1,1,1,1,2,2,2,2,4,4,4,4,4,6,6,6,6,7,8,8,9,9,10,12,12,12,13,14,15,16,17,18,19,19,20,21,22,24,25],
    [-1,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16,17,17,18,20,21,23,25,26,28,29,31,33,35,37,38,40,43,45,47,49],
    [-1,1,1,2,2,4,4,6,6,8,8,8,10,12,16,12,17,16,18,21,20,23,23,25,27,29,34,34,35,38,40,43,45,48,51,53,56,59,62,65,68],
    [-1,1,1,2,4,4,4,5,6,8,8,11,11,16,16,18,16,19,21,25,25,25,34,30,32,35,37,40,42,45,48,51,54,57,60,63,66,70,74,77,81]
  ];

  function numRawDataModules(ver) {
    var r = (16 * ver + 128) * ver + 64;
    if (ver >= 2) {
      var n = Math.floor(ver / 7) + 2;
      r -= (25 * n - 10) * n - 55;
      if (ver >= 7) r -= 36;
    }
    return r;
  }
  function numDataCodewords(ver, ecl) {
    return Math.floor(numRawDataModules(ver) / 8) - ECC_CW[ecl][ver] * NUM_BLOCKS[ecl][ver];
  }

  function gfMul(x, y) {
    var z = 0;
    for (var i = 7; i >= 0; i--) {
      z = (z << 1) ^ ((z >>> 7) * 0x11D);
      z ^= ((y >>> i) & 1) * x;
    }
    return z & 0xFF;
  }
  function rsDivisor(degree) {
    var result = new Uint8Array(degree); result[degree - 1] = 1;
    var root = 1;
    for (var i = 0; i < degree; i++) {
      for (var j = 0; j < degree; j++) {
        result[j] = gfMul(result[j], root);
        if (j + 1 < degree) result[j] ^= result[j + 1];
      }
      root = gfMul(root, 2);
    }
    return result;
  }
  function rsRemainder(data, divisor) {
    var result = new Uint8Array(divisor.length);
    for (var k = 0; k < data.length; k++) {
      var factor = data[k] ^ result[0];
      for (var i = 0; i < result.length - 1; i++) result[i] = result[i + 1];
      result[result.length - 1] = 0;
      for (var j = 0; j < result.length; j++) result[j] ^= gfMul(divisor[j], factor);
    }
    return result;
  }

  function toUtf8(str) {
    if (global.TextEncoder) return new TextEncoder().encode(str);
    var enc = unescape(encodeURIComponent(str)), b = new Uint8Array(enc.length);
    for (var i = 0; i < enc.length; i++) b[i] = enc.charCodeAt(i);
    return b;
  }

  function alignmentPositions(ver) {
    if (ver === 1) return [];
    var n = Math.floor(ver / 7) + 2;
    var step = (ver === 32) ? 26 : Math.ceil((ver * 4 + 4) / (n * 2 - 2)) * 2;
    var result = [6];
    for (var pos = ver * 4 + 10; result.length < n; pos -= step) result.splice(1, 0, pos);
    return result;
  }

  function reserveFormat(size, setFun) {
    for (var i = 0; i <= 5; i++) setFun(8, i, false);
    setFun(8, 7, false); setFun(8, 8, false); setFun(7, 8, false);
    for (i = 9; i < 15; i++) setFun(14 - i, 8, false);
    for (i = 0; i < 8; i++) setFun(size - 1 - i, 8, false);
    for (i = 8; i < 15; i++) setFun(8, size - 15 + i, false);
    setFun(8, size - 8, true); // immer dunkles Modul
  }

  function drawVersion(ver, size, setFun) {
    if (ver < 7) return;
    var rem = ver;
    for (var i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
    var bits = (ver << 12) | rem;
    for (i = 0; i < 18; i++) {
      var bit = ((bits >>> i) & 1) === 1;
      var a = size - 11 + i % 3, b = Math.floor(i / 3);
      setFun(a, b, bit); setFun(b, a, bit);
    }
  }

  function drawFormat(fmt, mask, size, mod) {
    var data = (fmt << 3) | mask, rem = data;
    for (var i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
    var bits = ((data << 10) | rem) ^ 0x5412;
    function g(i) { return ((bits >>> i) & 1) === 1; }
    for (i = 0; i <= 5; i++) mod[i][8] = g(i);
    mod[7][8] = g(6); mod[8][8] = g(7); mod[8][7] = g(8);
    for (i = 9; i < 15; i++) mod[8][14 - i] = g(i);
    for (i = 0; i < 8; i++) mod[8][size - 1 - i] = g(i);
    for (i = 8; i < 15; i++) mod[size - 15 + i][8] = g(i);
  }

  function drawData(data, size, mod, fun) {
    var i = 0;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (var vert = 0; vert < size; vert++) {
        for (var j = 0; j < 2; j++) {
          var x = right - j;
          var upward = ((right + 1) & 2) === 0;
          var y = upward ? size - 1 - vert : vert;
          if (!fun[y][x] && i < data.length * 8) {
            mod[y][x] = ((data[i >>> 3] >>> (7 - (i & 7))) & 1) === 1;
            i++;
          }
        }
      }
    }
  }

  function applyMask(mask, size, mod, fun) {
    for (var y = 0; y < size; y++) {
      for (var x = 0; x < size; x++) {
        if (fun[y][x]) continue;
        var inv = false;
        switch (mask) {
          case 0: inv = (x + y) % 2 === 0; break;
          case 1: inv = y % 2 === 0; break;
          case 2: inv = x % 3 === 0; break;
          case 3: inv = (x + y) % 3 === 0; break;
          case 4: inv = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
          case 5: inv = (x * y) % 2 + (x * y) % 3 === 0; break;
          case 6: inv = ((x * y) % 2 + (x * y) % 3) % 2 === 0; break;
          case 7: inv = (((x + y) % 2) + ((x * y) % 3)) % 2 === 0; break;
        }
        if (inv) mod[y][x] = !mod[y][x];
      }
    }
  }

  function matchPat(mod, y, x, horiz, pat) {
    for (var i = 0; i < 11; i++) {
      var v = horiz ? mod[y][x + i] : mod[y + i][x];
      if (v !== pat[i]) return false;
    }
    return true;
  }
  function penalty(size, mod) {
    var P = 0, N1 = 3, N2 = 3, N3 = 40, N4 = 10, x, y, run;
    for (y = 0; y < size; y++) {
      run = 1;
      for (x = 1; x < size; x++) {
        if (mod[y][x] === mod[y][x - 1]) run++;
        else { if (run >= 5) P += N1 + (run - 5); run = 1; }
      }
      if (run >= 5) P += N1 + (run - 5);
    }
    for (x = 0; x < size; x++) {
      run = 1;
      for (y = 1; y < size; y++) {
        if (mod[y][x] === mod[y - 1][x]) run++;
        else { if (run >= 5) P += N1 + (run - 5); run = 1; }
      }
      if (run >= 5) P += N1 + (run - 5);
    }
    for (y = 0; y < size - 1; y++) for (x = 0; x < size - 1; x++) {
      var c = mod[y][x];
      if (c === mod[y][x + 1] && c === mod[y + 1][x] && c === mod[y + 1][x + 1]) P += N2;
    }
    var p1 = [true, false, true, true, true, false, true, false, false, false, false];
    var p2 = [false, false, false, false, true, false, true, true, true, false, true];
    for (y = 0; y < size; y++) for (x = 0; x <= size - 11; x++)
      if (matchPat(mod, y, x, true, p1) || matchPat(mod, y, x, true, p2)) P += N3;
    for (x = 0; x < size; x++) for (y = 0; y <= size - 11; y++)
      if (matchPat(mod, y, x, false, p1) || matchPat(mod, y, x, false, p2)) P += N3;
    var dark = 0;
    for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (mod[y][x]) dark++;
    var ratio = dark * 100 / (size * size);
    P += Math.floor(Math.abs(ratio - 50) / 5) * N4;
    return P;
  }

  function encode(text, eclName) {
    var conf = ECL[eclName] || ECL.M;
    var ecl = conf.ord, fmt = conf.fmt;
    var data = toUtf8(text), ver, ccbits, need, cap;

    for (ver = 1; ver <= 40; ver++) {
      ccbits = (ver <= 9) ? 8 : 16;
      need = 4 + ccbits + data.length * 8;
      if (need <= numDataCodewords(ver, ecl) * 8) break;
    }
    if (ver > 40) throw new Error('Daten zu lang für einen QR-Code.');

    // Fehlerkorrektur anheben, solange dieselbe Version reicht
    for (var ee = 3; ee > ecl; ee--) {
      if (4 + ((ver <= 9) ? 8 : 16) + data.length * 8 <= numDataCodewords(ver, ee) * 8) {
        ecl = ee; fmt = FMT[ee]; break;
      }
    }

    var bits = [];
    function append(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    append(4, 4);
    append(data.length, (ver <= 9) ? 8 : 16);
    for (var i = 0; i < data.length; i++) append(data[i], 8);

    var capBits = numDataCodewords(ver, ecl) * 8;
    append(0, Math.min(4, capBits - bits.length));
    while (bits.length % 8 !== 0) bits.push(0);
    for (var pad = 0xEC; bits.length < capBits; pad ^= 0xEC ^ 0x11) append(pad, 8);

    var dataCw = new Uint8Array(bits.length / 8);
    for (i = 0; i < dataCw.length; i++) {
      var byte = 0;
      for (var j = 0; j < 8; j++) byte = (byte << 1) | bits[i * 8 + j];
      dataCw[i] = byte;
    }

    var numBlocks = NUM_BLOCKS[ecl][ver], eccLen = ECC_CW[ecl][ver];
    var rawCw = Math.floor(numRawDataModules(ver) / 8);
    var numShort = numBlocks - rawCw % numBlocks;
    var shortLen = Math.floor(rawCw / numBlocks) - eccLen;
    var divisor = rsDivisor(eccLen);

    var blocks = [], off = 0;
    for (var b = 0; b < numBlocks; b++) {
      var dlen = shortLen + (b < numShort ? 0 : 1);
      var dat = dataCw.slice(off, off + dlen); off += dlen;
      blocks.push({ dat: dat, ecc: rsRemainder(dat, divisor) });
    }
    var out = [];
    for (i = 0; i < shortLen + 1; i++)
      for (b = 0; b < numBlocks; b++)
        if (i < blocks[b].dat.length) out.push(blocks[b].dat[i]);
    for (i = 0; i < eccLen; i++)
      for (b = 0; b < numBlocks; b++) out.push(blocks[b].ecc[i]);
    var allCw = new Uint8Array(out);

    var size = ver * 4 + 17;
    var mod = [], fun = [];
    for (var y = 0; y < size; y++) {
      mod.push(new Array(size).fill(false));
      fun.push(new Array(size).fill(false));
    }
    function setFun(x, yy, d) { if (x >= 0 && x < size && yy >= 0 && yy < size) { mod[yy][x] = d; fun[yy][x] = true; } }

    function finder(cx, cy) {
      for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
        var dist = Math.max(Math.abs(dx), Math.abs(dy));
        setFun(cx + dx, cy + dy, dist !== 2 && dist !== 4);
      }
    }
    finder(3, 3); finder(size - 4, 3); finder(3, size - 4);

    for (i = 0; i < size; i++) {
      if (!fun[6][i]) { mod[6][i] = (i % 2 === 0); fun[6][i] = true; }
      if (!fun[i][6]) { mod[i][6] = (i % 2 === 0); fun[i][6] = true; }
    }

    var ap = alignmentPositions(ver), na = ap.length;
    for (i = 0; i < na; i++) for (var k = 0; k < na; k++) {
      if ((i === 0 && k === 0) || (i === 0 && k === na - 1) || (i === na - 1 && k === 0)) continue;
      for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++)
        setFun(ap[i] + dx, ap[k] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
    }

    reserveFormat(size, setFun);
    drawVersion(ver, size, setFun);
    drawData(allCw, size, mod, fun);

    var best = 0, bestP = Infinity;
    for (var m = 0; m < 8; m++) {
      applyMask(m, size, mod, fun);
      drawFormat(fmt, m, size, mod);
      var p = penalty(size, mod);
      if (p < bestP) { bestP = p; best = m; }
      applyMask(m, size, mod, fun); // rückgängig
    }
    applyMask(best, size, mod, fun);
    drawFormat(fmt, best, size, mod);

    return { size: size, modules: mod, version: ver };
  }

  global.QRCodeGen = { encode: encode };
})(typeof window !== 'undefined' ? window : this);
