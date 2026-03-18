# Symfony 8 Franken API (PoC)

REST API for loan management (users, loans, payments). PHP 8.5, PostgreSQL, Docker Compose.

**Context:** this is a **PoC** (not a production solution). It was built to evaluate [FrankenPHP](https://frankenphp.dev/) on a realistic Symfony API (users, loans, payments) and to keep the comparison reproducible (Nginx+PHP-FPM vs FrankenPHP) with a concrete architecture and benchmark scenario.

Most commands below are available via **Make**; run `make help` to list them.

---

## Tech stack

- PHP **8.5**
- Symfony **8.0**
- Runtime: **PHP-FPM + Nginx** vs **FrankenPHP (worker mode)**
- DB: **PostgreSQL 17**
- ORM & migrations: **Doctrine ORM + Doctrine Migrations**
- Async/concurrency: **Amp futures** (fan-out for exchange rate providers)
- Money: **brick/money** (minor units, rounding)
- API docs: **NelmioApiDocBundle** (`/api/doc`, `/api/doc.json`)
- Tooling: **PHPUnit**, **Psalm (level 1)**, **PHP-CS-Fixer**, **Docker Compose**, **Make**

---

## Two environments: PHP-FPM + Nginx vs FrankenPHP

There are two separate Docker Compose files — for the standard stack and for FrankenPHP.

| File | Stack | App port | DB port |
|------|-------|----------|---------|
| `docker-compose.yml` | PHP 8.5-FPM + Nginx | 8080 | 5433 |
| `docker-compose.franken.yml` | FrankenPHP (Caddy) | 8081 | 5434 |

Each Compose has its **own PostgreSQL volume** (`postgres_data` and `franken_postgres_data`), so you can run both setups at the same time without conflicts.

## Configuration (quick start)

This PoC includes environment templates for quick local setup:
`.env.dist`, `.env.dev.dist`, `.env.test.dist`.

After cloning, copy the templates to Symfony env files:

```bash
cp .env.dist .env
cp .env.dev.dist .env.dev
cp .env.test.dist .env.test
```

### Running the standard stack (PHP-FPM + Nginx)

From scratch:

```bash
make init
```

Or step by step: `make up`, `make install`, `make db-migrate`.

App: **http://localhost:8080**

### Running FrankenPHP for testing

From scratch:

```bash
make init-franken
```

Or step by step: `make up-franken`, `make install-franken`, `make db-migrate-franken`.

App: **http://localhost:8081**

Optional env vars for benchmarking (in `docker-compose.franken.yml`):

- `FRANKEN_WORKERS` — number of worker processes (default 1).
- `FRANKEN_MAX_REQUEST_RESTART` — after how many requests to restart a worker (default 1000 in Caddyfile).

For benchmarks you can switch to prod in compose:

```yaml
APP_ENV: prod
APP_DEBUG: 0
FRANKEN_WORKERS: 8
```

### Benchmark: Nginx+FPM vs FrankenPHP

**Benchmark configuration:**

- **PHP-FPM:** `pm.max_children = 50` (in `docker/php/Dockerfile`).
- **FrankenPHP:** `FRANKEN_WORKERS: 8`, `FRANKEN_MAX_REQUEST_RESTART: 700` (in `docker-compose.franken.yml`).

Scenario: **users** (create users, loans, payments, lists).

**Environment:** benchmarks were run in Docker on a typical dev machine. The numbers are for **relative comparison** (FPM vs Franken under the same load), not absolute production capacity — tune and re-run on your target hardware if you need production estimates.

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  BENCHMARK COMPARISON

                                     Nginx+FPM      FrankenPHP     Delta
  ────────────────────────────────────────────────────────────────────
  Total requests                         11796           11796        0%
  Requests/sec                    175.513136/s    278.626084/s      +59%
  Errors                            0req 0.00%      0req 0.00%       —
  ────────────────────────────────────────────────────────────────────
  HTTP response time
    avg                               181.46ms        113.03ms      +61%
    med                               187.47ms         109.2ms      +72%
    p(95)                             311.79ms         191.3ms      +63%
    p(99)                              431.9ms        262.77ms      +64%
  ────────────────────────────────────────────────────────────────────
  Custom metrics (avg / p95)
    create user avg                   223.27ms        119.68ms      +87%
    create user p95                   352.59ms        173.22ms     +104%
    apply loan avg                    161.34ms        116.46ms      +39%
    apply loan p95                    433.74ms        326.12ms      +33%
    make payment avg                  145.44ms         89.59ms      +62%
    make payment p95                  288.84ms        152.43ms      +89%
    get loan avg                      114.24ms         80.63ms      +42%
    get loan p95                      253.21ms        134.67ms      +88%
    list users avg                    212.52ms         142.7ms      +49%
    list users p95                    327.17ms        219.52ms      +49%
  ────────────────────────────────────────────────────────────────────
  Iteration avg                       350.25ms        222.92ms      +57%
  Iterations/sec (total)           92.250038/s    146.446399/s      +59%

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

In this test FrankenPHP yields ~+59% requests/sec and better latencies (avg, med, p95, p99).

**Run benchmarks (Make):**
- Compare Nginx+FPM vs FrankenPHP: `make bench-compare`
- Run only one stack: `make bench m=fpm` or `make bench m=franken`

**About the benchmark config:** the values in this repo (`pm.max_children = 50`, `FRANKEN_WORKERS = 8`) were picked as a good baseline for **my setup** to make the comparison reproducible. If you run this on different hardware, tune and re-run. (k6 runs via Docker image `grafana/k6`).

---

## Architecture

```
src/
├── Domain/              # Core: entities, value objects, repository interfaces, exceptions
│   ├── User/
│   ├── Loan/
│   └── Shared/          # TransactionalRepositoryInterface, SupportedCurrency, etc.
├── Application/         # Use cases — application scenarios
│   ├── User/UseCase/
│   └── Loan/UseCase/
├── Infrastructure/      # Implementations: Doctrine, HTTP clients, listeners
│   ├── Persistence/     # Repositories, TransactionalTrait
│   ├── Client/          # Exchange rate providers
│   └── Listener/
└── Presentation/        # HTTP: controllers, DTOs, attributes
    └── Controller/
```

**Dependency rule:** dependencies point inward. Domain has no framework dependencies (except Doctrine mapping attributes). Application depends on Domain. Infrastructure implements Domain interfaces. Presentation calls Application.

### Architecture highlights

- **Ports & adapters (hexagonal-ish)**: repositories and integrations are expressed as **Domain interfaces**, implemented in `src/Infrastructure/` and wired via DI aliases in `config/services.yaml`.
- **Use cases as application boundary**: `src/Application/*/UseCase/*` contains the main business flows; transactional boundaries live at the use-case level.
- **Async fan-out for exchange rates**: `ExchangeRateService` runs multiple `ExchangeRateProviderInterface` implementations **concurrently** via Amp futures, soft-fails individual providers, then picks the best rate via `BestRateSelector` (fallback = 503 when none respond). This pattern is essential for integrating with multiple slow external APIs (like carrier gateways) without blocking the main application thread.
- **Strategy + resolver via DI tags**: payment calculation uses multiple strategies, selected through a resolver (`tagged_iterator`).
- **HTTP concerns isolated**: controllers + DTOs live in `src/Presentation/`; kernel listeners translate domain errors and DTO responses into consistent HTTP JSON.

---

## Design decisions

- **Layered (Clean) architecture** — keeps domain logic independent of HTTP and persistence, makes use cases testable and allows swapping infrastructure (e.g. another DB or queue) without touching domain or application layer.
- **Transaction only in MakePaymentUseCase** — a payment updates the loan (balance, `paid_months`) and inserts a payment row; both must commit together. Other flows (e.g. create loan) use a single `save()` and don’t need an explicit transaction boundary.
- **`findByIdForUpdate` for payments** — when recording a payment we use a pessimistic write lock on the loan row so concurrent payments for the same loan are serialized and balance updates stay correct; see [Locks (pessimistic write)](#locks-pessimistic-write) below.

---

## Transactions

Operations that change multiple entities or must be atomic run inside a single transaction via a domain interface:

- **`TransactionalRepositoryInterface`** (Domain) — single method: `executeTransactional(callable $operation): mixed`.
- **Implementation** — `DoctrineLoanRepository` + `TransactionalTrait`: calls `$this->em->wrapInTransaction($operation)`.


**Where it’s used:** only in **MakePaymentUseCase**. Recording a payment updates the loan (remaining balance, `paid_months`) and inserts a row in `payments`, so everything runs inside one `executeTransactional(...)` call.

Other use cases (e.g. creating a loan) currently use a single `save()` call without an explicit transaction.

---

## Locks (pessimistic write)

To avoid race conditions when multiple payments hit the same loan at once, the loan is read with a **write lock** when applying a payment:

- **LoanRepositoryInterface** has two methods:
  - `findById(LoanId)` — normal read.
  - `findByIdForUpdate(LoanId)` — read with `LockMode::PESSIMISTIC_WRITE` (Doctrine issues `SELECT ... FOR UPDATE`).

In **MakePaymentUseCase**, inside `executeTransactional`, the code uses `findByIdForUpdate`: the transaction holds the loan row until commit, so other payment requests for the same loan block. After computing the new balance and creating the payment, changes are flushed and committed in the same transaction.

Summary:

- **Transaction** — atomicity boundary (one payment = one commit).
- **PESSIMISTIC_WRITE** — prevents concurrent updates to the same loan.

---

## Requirements

- Docker and Docker Compose
- Make (optional; used for the commands in this README — run `make help` to list targets)

---

## API Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| GET | /api/health | Health check |
| GET | /api/users | List users |
| GET | /api/users/{id} | Get user by UUID |
| POST | /api/users | Create user |
| POST | /api/users/{userId}/loans | Create loan |
| GET | /api/users/{userId}/loans | List user's loans |
| GET | /api/loans/{id} | Loan details |
| POST | /api/loans/{id}/payments | Record payment |
| GET | /api/loans/{id}/payments | List loan payments |

OpenAPI: **/api/doc**, JSON: **/api/doc.json**.

### Example: create user

```bash
curl -X POST http://localhost:8080/api/users \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe", "email": "john@example.com"}'
```

(Use port **8081** for FrankenPHP.)

---

## Testing

```bash
# Standard stack (FPM)
make test

# FrankenPHP stack
make test-franken
```

## Quality (formatter + static analysis)

```bash
# PHP-CS-Fixer
make cs-check        # dry-run (FPM)
make cs              # apply fixes (FPM)
make cs-check-franken
make cs-franken

# Psalm: Static analysis at Level 1 
make psalm
make psalm-franken
```

---

## Environment variables

| Variable | Default |
|----------|---------|
| APP_ENV | dev |
| APP_SECRET | change_me_to_a_random_secret_value |
| DATABASE_URL | postgresql://app:secret@postgres:5432/app_db?serverVersion=17&charset=utf8 |

In `docker-compose.franken.yml` the app service is `frankenphp`; the DB host inside the network is `postgres`.
