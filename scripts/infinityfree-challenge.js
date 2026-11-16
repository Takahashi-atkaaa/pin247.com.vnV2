#!/usr/bin/env node
/**
 * =============================================================================
 *  PIN247 — giải JS anti-bot challenge của InfinityFree
 *
 *  InfinityFree (và một số shared hosting) chèn một trang "challenge" vào mọi
 *  request thiếu cookie `__test`. Trang này chứa:
 *
 *      <script src="/aes.js"></script>
 *      <script>
 *        var a = toNumbers("<key hex>"),
 *            b = toNumbers("<iv  hex>"),
 *            c = toNumbers("<cipher hex>");
 *        document.cookie = "__test=" + toHex(slowAES.decrypt(c, 2, a, b)) + "...";
 *        location.href = "<url>?i=1";
 *      </script>
 *
 *  Script này:
 *    1. Tải trang challenge
 *    2. Trích key / iv / ciphertext
 *    3. Dùng CHÍNH file aes.js của hosting để giải (không reimplement AES)
 *    4. Trả về cookie `__test` để dùng cho các request sau
 *
 *  CÁCH DÙNG:
 *      node scripts/infinityfree-challenge.js <url>
 *      → in ra: __test=<cookie value>
 *
 *  LƯU Ý:
 *    - Challenge hiện tại là TĨNH (cùng key/iv mọi lần), nhưng script vẫn
 *      đọc lại từ trang thực tế nên vẫn đúng nếu hosting đổi.
 *    - Cookie sống ~6 giờ (max-age=21600).
 * =============================================================================
 */

import http from 'http';
import https from 'https';
import vm from 'vm';

const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
  '(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

/**
 * HTTP GET đơn giản (theo dõi redirect).
 */
function httpGet(url, headers = {}, redirects = 0) {
  return new Promise((resolve, reject) => {
    const target = new URL(url);
    const mod = target.protocol === 'https:' ? https : http;

    const req = mod.request(
      {
        hostname: target.hostname,
        port: target.port || (target.protocol === 'https:' ? 443 : 80),
        path: target.pathname + target.search,
        method: 'GET',
        // InfinityFree dùng chứng thư do Cloudflare/Google Trust Services ký.
        // Thiếu intermediate => bỏ qua verify (domain demo, dữ liệu public).
        rejectUnauthorized: false,
        headers: { 'User-Agent': UA, ...headers },
      },
      (res) => {
        const chunks = [];

        res.on('data', (c) => chunks.push(c));
        res.on('end', () => {
          const body = Buffer.concat(chunks).toString('utf8');

          if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location && redirects < 5) {
            const next = new URL(res.headers.location, url).toString();

            resolve(httpGet(next, headers, redirects + 1));
            return;
          }

          resolve({ status: res.statusCode, headers: res.headers, body });
        });
      }
    );

    req.on('error', reject);
    req.setTimeout(60000, () => req.destroy(new Error('Timeout')));
    req.end();
  });
}

/**
 * Trích key/iv/ciphertext từ trang challenge.
 */
function parseChallenge(html) {
  const matches = [...html.matchAll(/toNumbers\("([a-f0-9]+)"\)/g)].map((m) => m[1]);

  if (matches.length < 3) {
    return null;
  }

  const [key, iv, cipher] = matches;

  return { key, iv, cipher };
}

/**
 * Giải challenge bằng chính aes.js của hosting.
 */
function decryptChallenge(aesJs, challenge) {
  const sandbox = { slowAES: null, console };
  const context = vm.createContext(sandbox);

  vm.runInContext(aesJs, context);

  if (! sandbox.slowAES) {
    throw new Error('aes.js không define slowAES');
  }

  const toNumbers = (hex) => hex.match(/(..)/g).map((b) => parseInt(b, 16));

  const plain = sandbox.slowAES.decrypt(
    toNumbers(challenge.cipher),
    2, // CFB
    toNumbers(challenge.key),
    toNumbers(challenge.iv)
  );

  return Buffer.from(plain).toString('hex');
}

async function main() {
  const url = process.argv[2] || 'https://tunganhahihi.io.vn/';

  console.error(`[challenge] Tải ${url} ...`);

  const first = await httpGet(url);
  const challenge = parseChallenge(first.body);

  if (! challenge) {
    // Không có challenge => hosting đã cho qua.
    console.error('[challenge] Không thấy challenge — server đã trả nội dung thật.');
    process.stdout.write('');
    return;
  }

  console.error(`[challenge] Tìm thấy challenge (key=${challenge.key.slice(0, 8)}…)`);

  // Tải aes.js từ cùng host.
  const aesUrl = new URL('/aes.js', url).toString();
  const aesResponse = await httpGet(aesUrl);

  if (aesResponse.status !== 200 || ! aesResponse.body.includes('slowAES')) {
    throw new Error(`Không tải được ${aesUrl} (HTTP ${aesResponse.status})`);
  }

  const cookieValue = decryptChallenge(aesResponse.body, challenge);

  console.error(`[challenge] Giải xong → __test=${cookieValue.slice(0, 16)}…`);
  process.stdout.write(`__test=${cookieValue}`);
}

main().catch((err) => {
  console.error(`[challenge] LỖI: ${err.message}`);
  process.exit(1);
});
