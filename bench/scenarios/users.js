import http from 'k6/http';
import { check } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

const VIRTUAL_USERS = 10;
const PAYMENTS_PER_LOAN = 20;

const createUserDuration = new Trend('create_user_duration', true);
const applyLoanDuration = new Trend('apply_loan_duration', true);
const makePaymentDuration = new Trend('make_payment_duration', true);
const crossCurrencyPaymentDuration = new Trend('cross_currency_payment_duration', true);
const getLoanDuration = new Trend('get_loan_duration', true);
const listUsersDuration = new Trend('list_users_duration', true);
const flowErrors = new Counter('flow_errors');

export const options = {
  scenarios: {
    create_users_only: {
      executor: 'shared-iterations',
      vus: VIRTUAL_USERS,
      iterations: 2000,
      exec: 'createUserOnly',
      maxDuration: '10m',
    },
    full_loan_flow: {
      executor: 'shared-iterations',
      vus: VIRTUAL_USERS,
      iterations: 200,
      exec: 'fullLoanFlow',
      maxDuration: '10m',
    },
    list_users: {
      executor: 'shared-iterations',
      vus: VIRTUAL_USERS,
      iterations: 2000,
      exec: 'listUsers',
      maxDuration: '10m',
    },
    mixed_reads: {
      executor: 'shared-iterations',
      vus: VIRTUAL_USERS,
      iterations: 2000,
      exec: 'mixedReads',
      maxDuration: '10m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.10'],
  },
};

function randomString(len) {
  const chars = 'abcdefghijklmnopqrstuvwxyz';
  let result = '';
  for (let i = 0; i < len; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

const JSON_HEADERS = { headers: { 'Content-Type': 'application/json' } };

function doCreateUser() {
  const user = {
    name: `User_${randomString(8)}`,
    email: `${randomString(6)}_${Date.now()}_${randomInt(0, 99999)}@bench.test`,
  };
  const res = http.post(`${BASE_URL}/api/users`, JSON.stringify(user), JSON_HEADERS);
  createUserDuration.add(res.timings.duration);

  const ok = check(res, { 'create user: 201': (r) => r.status === 201 });
  if (!ok) { flowErrors.add(1); return null; }

  try { return JSON.parse(res.body).id; } catch { return null; }
}

function doApplyLoan(userId) {
  const loan = {
    amountMinor: randomInt(1000000, 10000000),
    currency: 'EUR',
  };
  const res = http.post(`${BASE_URL}/api/users/${userId}/loans`, JSON.stringify(loan), JSON_HEADERS);
  applyLoanDuration.add(res.timings.duration);

  const ok = check(res, { 'apply loan: 201': (r) => r.status === 201 });
  if (!ok) { flowErrors.add(1); return null; }

  try { return JSON.parse(res.body).id; } catch { return null; }
}

function doMakePayment(loanId, crossCurrency = false) {
  const minPaymentRes = http.get(`${BASE_URL}/api/loans/${loanId}/payments/min-payment`, JSON_HEADERS);
  const minPaymentOk = check(minPaymentRes, { 'min-payment: 200': (r) => r.status === 200 });
  if (!minPaymentOk) {
      flowErrors.add(1);
      return false;
  }

  let minPayment;
  try {
      minPayment = JSON.parse(minPaymentRes.body);
  } catch {
      flowErrors.add(1); return false;
  }

  const loanCurrency = minPayment.currency;
  const minAmountMinor = minPayment.minPaymentAmountMinor;

  const paymentCurrency = crossCurrency ? 'USD' : 'EUR';
  const multiplier = paymentCurrency === loanCurrency ? 1 : 2; // safe overpay for FX cases
  const amountMinor = Math.max(1, Math.floor(minAmountMinor * multiplier));

  const body = JSON.stringify({
    amountMinor,
    currency: paymentCurrency,
  });

  const res = http.post(`${BASE_URL}/api/loans/${loanId}/payments`, body, JSON_HEADERS);

  if (crossCurrency) {
    crossCurrencyPaymentDuration.add(res.timings.duration);
  } else {
    makePaymentDuration.add(res.timings.duration);
  }

  const ok = check(res, {
    'payment: 201 or 409': (r) => r.status === 201 || r.status === 409,
  });
  if (!ok) flowErrors.add(1);

  return res.status === 201;
}

function doGetLoan(loanId) {
  const res = http.get(`${BASE_URL}/api/loans/${loanId}`);
  getLoanDuration.add(res.timings.duration);
  check(res, { 'get loan: 200': (r) => r.status === 200 });
}

export function fullLoanFlow() {
  const userId = doCreateUser();
  if (!userId) return;

  const loanId = doApplyLoan(userId);
  if (!loanId) return;

  for (let i = 0; i < PAYMENTS_PER_LOAN; i++) {
    const useCrossCurrency = i % 5 === 0;
    const continued = doMakePayment(loanId, useCrossCurrency);

    if (!continued) break;
  }

  doGetLoan(loanId);
}

export function createUserOnly() {
  doCreateUser();
}

export function listUsers() {
  const res = http.get(`${BASE_URL}/api/users`);
  listUsersDuration.add(res.timings.duration);
  check(res, {
    'list users: 200': (r) => r.status === 200,
    'list users: is array': (r) => {
      try { return Array.isArray(JSON.parse(r.body)); } catch { return false; }
    },
  });
}

function fetchRandomUserId() {
  const res = http.get(`${BASE_URL}/api/users`);
  if (res.status !== 200) return null;
  try {
    const users = JSON.parse(res.body);
    if (!Array.isArray(users) || users.length === 0) return null;
    return users[Math.floor(Math.random() * users.length)].id;
  } catch { return null; }
}

export function mixedReads() {
  const roll = Math.random();

  if (roll < 0.4) {
    listUsers();
  } else if (roll < 0.7) {
    const userId = fetchRandomUserId();
    if (userId) {
      const res = http.get(`${BASE_URL}/api/users/${userId}/loans`);
      check(res, { 'list loans: 200': (r) => r.status === 200 });
    }
  } else {
    const userId = fetchRandomUserId();
    if (userId) {
      const res = http.get(`${BASE_URL}/api/users/${userId}`);
      check(res, { 'get user: 200': (r) => r.status === 200 });
    }
  }
}
