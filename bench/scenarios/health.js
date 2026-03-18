import http from 'k6/http';
import { check } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

const healthDuration = new Trend('health_check_duration', true);

export const options = {
  scenarios: {
    health_rps: {
      executor: 'constant-arrival-rate',
      rate: 200,
      timeUnit: '1s',
      duration: '15s',
      preAllocatedVUs: 50,
      maxVUs: 100,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    health_check_duration: ['p(99)<100'],
  },
};

export default function () {
  const res = http.get(`${BASE_URL}/api/health`);

  healthDuration.add(res.timings.duration);

  check(res, {
    'health: status is 200': (r) => r.status === 200,
    'health: status ok': (r) => {
      try { return JSON.parse(r.body).status === 'ok'; } catch { return false; }
    },
  });
}
