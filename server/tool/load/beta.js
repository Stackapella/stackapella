// The beta's load test (docs/performance.md, "Load test"). k6 is its own binary (brew install k6,
// or the k6 release), never an app dependency. Run by the coordinator against the beta only:
//
//   k6 run -e BASE=https://beta.stackapella.com server/tool/load/beta.js
//   k6 run -e BASE=... -e TOKENS=sat_a,sat_b server/tool/load/beta.js   # signed-in reads too
//
// TOKENS are app access tokens of the beta's test accounts (an hour each), one per signed-in
// virtual user. From one machine, the API's per-network limits (60 signed-out requests a minute,
// 120 for the public pages) are part of what's measured: the "limited" rate counts the 429s, which
// (like a closed feature's 404) aren't failures, and aren't in the timings. For more load than one
// network may send, run it from several machines at once.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = (__ENV.BASE || '').replace(/\/$/, '');
const TOKENS = (__ENV.TOKENS || '').split(',').filter(Boolean);
const limited = new Rate('limited');
const revalidated = new Rate('revalidated');
const catalogue = new Trend('catalogue_ms', true);
// The served answers' timings, by kind: a 429 answers at once, so it would flatter them.
const timings = { public: new Trend('public_ms', true), signed_in: new Trend('signed_in_ms', true) };

// Only the beta, or a server on this machine: anything else (production above all) is refused.
if (!/^https?:\/\/(beta\.stackapella\.com|localhost|127\.0\.0\.1)(:\d+)?$/.test(BASE)) {
  throw new Error('Set BASE to https://beta.stackapella.com or a local server (never production).');
}

// A limit's 429 and a closed feature's 404 are expected answers, not failures.
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }, 404, 429));

export const options = {
  // A redirect (to production, or to sign in) is answered as it is, never followed.
  maxRedirects: 0,
  scenarios: {
    // Visitors: the public reads, under one network's limit.
    visitors: { executor: 'constant-arrival-rate', rate: 50, timeUnit: '1m', duration: '5m', preAllocatedVUs: 5, exec: 'visitor' },
    // Singers: the signed-in reads the app makes on opening, if tokens were given.
    ...(TOKENS.length ? { singers: { executor: 'per-vu-iterations', vus: TOKENS.length, iterations: 20, maxDuration: '10m', exec: 'singer' } } : {}),
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    public_ms: ['p(95)<400', 'p(99)<1000'],
    signed_in_ms: ['p(95)<600', 'p(99)<1500'],
    catalogue_ms: ['p(95)<400'],
  },
};

const JSON_HEADERS = { Accept: 'application/json', 'X-App-Version': 'load-test' };

function get(path, kind, headers = {}) {
  const response = http.get(`${BASE}${path}`, { headers: { ...JSON_HEADERS, ...headers }, tags: { kind, name: path.split('?')[0] } });
  limited.add(response.status === 429);
  if (response.status !== 429) {
    timings[kind].add(response.timings.duration);
  }
  return response;
}

export function visitor() {
  get('/api/auth/config', 'public');
  const list = get('/api/templates', 'public');
  if (list.status !== 429) {
    catalogue.add(list.timings.duration);
  }
  check(list, { 'catalogue 200': (r) => r.status === 200 || r.status === 429 });
  // A repeat read with the ETag: a 304 (docs/performance.md, "Cache headers").
  const etag = list.headers.Etag || list.headers.ETag;
  if (etag) {
    revalidated.add(get('/api/templates', 'public', { 'If-None-Match': etag }).status === 304);
  }
  get('/api/billing/prices', 'public');
  for (const path of ['/api/explore', '/api/ideas', '/api/choirs/amazing-grace']) {
    // Closed features answer 404: that's fine, it's still the server's work.
    check(get(path, 'public'), { [`${path} answers`]: (r) => [200, 404, 429].includes(r.status) });
  }
  sleep(1);
}

export function singer() {
  const token = TOKENS[(__VU - 1) % TOKENS.length];
  const auth = { Authorization: `Bearer ${token}` };
  for (const path of ['/api/account', '/api/billing', '/api/account/learning', '/api/sync?since=0', '/api/templates']) {
    check(get(path, 'signed_in', auth), { [`${path} 200`]: (r) => r.status === 200 });
  }
  sleep(3);
}
