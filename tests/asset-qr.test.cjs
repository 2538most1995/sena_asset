const assert = require('node:assert/strict');
const qr = require('../assets/asset-qr.js');
assert.equal(qr.url('https://krumost.com/SENA_Asset/equipment.php?search=x',123,2568),'https://krumost.com/SENA_Asset/asset.php?id=123&year=2568');
assert.equal(qr.url('http://localhost:8099/equipment.php',456,2569),'http://localhost:8099/asset.php?id=456&year=2569');
assert.notEqual(qr.url('https://example.com/equipment.php',1,2568), qr.url('https://example.com/equipment.php',2,2568));
assert.throws(()=>qr.url('https://example.com/equipment.php',0,2568));
console.log('QR URL tests passed');
