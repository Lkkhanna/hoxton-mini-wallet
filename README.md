# 🏦 Hoxton Mini Wallet

A minimal full-stack financial system that simulates account management, money transfers, transaction tracking, and balance visibility — built with **Laravel 12**, **Vue 2**, and **MySQL 8**.

## Tech Stack

| Layer    | Technology           |
|----------|----------------------|
| Backend  | Laravel 12 (PHP 8.2) |
| Frontend | Vue 2.7 + Axios      |
| Database | MySQL 8.0            |
| DevOps   | Docker Compose       |

---

## 📌 Assumptions

- Authentication and user management are out of scope (confirmed with assessment team)
- Single currency system (no FX handling)
- Account IDs are globally unique and user-provided
- No concurrent modification outside this system (no external ledger writers)
- Transactions are synchronous and expected to complete quickly

---

## 🚀 Quick Start (Docker)

### Prerequisites
- [Docker](https://www.docker.com/get-started) & Docker Compose installed

### Setup & Run

```bash
# 1. Clone the repository
git clone https://github.com/Lkkhanna/hoxton-mini-wallet.git
cd hoxton-mini-wallet

# 2. Create local environment files
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

# 3. Build and start all services
docker compose up --build

# Or use the Makefile
make build
make up
```

### Access the Application

| Service     | URL                       |
|-------------|---------------------------|
| Frontend    | http://localhost:8080     |
| Backend API | http://localhost:8000/api |
| MySQL       | localhost:3306            |

> **Note:** The first startup takes ~60 seconds as it builds containers, installs dependencies, runs migrations, and seeds the database with 3 demo accounts plus historical ledger activity.

### Environment Configuration

- `backend/.env` is the primary Laravel application config
- `frontend/.env` is the primary frontend runtime config
- `docker-compose.yml` is intentionally limited to container orchestration concerns
- For Docker, the backend uses `DB_HOST=db`; if you run Laravel outside Docker, switch that host accordingly

### Default Seeded Accounts

The seed data includes 12 ledger entries for `ACC001`, so transaction history pagination is visible immediately with the default `per_page=10` setting. Seeded transfers use the same `Transfer to ...` / `Transfer from ...` wording as live transfers.

| Account ID | Name          | Current Seeded Balance |
|------------|---------------|------------------------|
| ACC001     | Alice Johnson | $10,000.00             |
| ACC002     | Bob Smith     | $5,000.00              |
| ACC003     | Charlie Brown | $7,500.00              |

---

## 📡 API Documentation

### Base URL: `http://localhost:8000/api`

### 1. List Accounts
```
GET /accounts
```
**Response 200:**
```json
{
  "success": true,
  "message": "Accounts retrieved successfully.",
  "data": [
    {
      "account_id": "ACC001",
      "name": "Alice Johnson",
      "balance": "10000.00",
      "created_at": "2026-04-06T12:00:00+00:00"
    }
  ]
}
```

### 2. Create Account
```
POST /accounts
Content-Type: application/json

{
  "account_id": "ACC004",
  "name": "Diana Prince"
}
```
**Response 201:** Created account object with normalised `account_id` and zero balance  
**Response 409:** Account already exists  
**Response 422:** Validation errors (invalid format, missing required fields)

### 3. Get Balance
```
GET /accounts/{account_id}/balance
```
**Response 200:**
```json
{
  "success": true,
  "message": "Account balance retrieved successfully.",
  "data": {
    "account_id": "ACC001",
    "balance": "10000.00"
  }
}
```
**Response 404:** Account not found

### 4. Transfer Money
```
POST /transfers
Content-Type: application/json

{
  "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
  "from_account_id": "ACC001",
  "to_account_id": "ACC002",
  "amount": 250.00
}
```
**Response 201:** Transfer created successfully
```json
{
  "success": true,
  "message": "Transfer completed successfully.",
  "data": {
    "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
    "from_account_id": "ACC001",
    "to_account_id": "ACC002",
    "amount": "250.00",
    "status": "completed",
    "idempotency_status": "created",
    "created_at": "2026-04-06T12:00:00+00:00"
  },
  "meta": {
    "idempotency": {
      "replayed": false
    }
  }
}
```
**Response 200:** Repeated identical `transaction_id` returns the original transfer result  
**Response 409:** Reusing a `transaction_id` for a different transfer payload  
**Response 422:** Validation error (insufficient funds, invalid account, etc.)

### 5. Transaction History
```
GET /accounts/{account_id}/transactions
```
**Response 200:**
```json
{
  "success": true,
  "message": "Transaction history retrieved successfully.",
  "data": [
    {
      "id": 1,
      "transaction_id": "550e8400-...",
      "type": "debit",
      "amount": "250.00",
      "counterparty": "ACC002",
      "description": "Transfer to ACC002",
      "timestamp": "2026-04-06T12:00:00+00:00"
    }
  ]
}
```

---

## 🏗️ Architecture & Design Decisions

### Single-Dashboard Frontend

The exercise only requires four user capabilities on the frontend:
- select or enter an account
- view account balance
- perform a transfer
- view transaction history

Because those workflows are tightly related, the UI is intentionally implemented as a single dashboard view instead of splitting them into multiple pages. This keeps the interaction flow simple while still preserving separation of concerns in code:

- `App.vue` provides the global shell and styles
- `views/DashboardView.vue` owns page-level state and orchestration
- `components/*` remain focused on individual UI concerns
- `services/api.js` isolates HTTP concerns from presentation logic

This keeps the product small but avoids turning the root component into an unstructured monolith.

### State Management

State is managed through Vue's built-in reactive system with a single-owner pattern. `DashboardView` is the sole source of truth for all application state — accounts, selected account, balance, transactions, loading flags, and error states. Child components are stateless: they receive data via props and communicate changes via events, never managing shared state themselves.

Vuex/Pinia was considered and deliberately omitted. For a single-page dashboard with one primary data flow, a shared store adds indirection without benefit. If the app grew to multiple pages with shared cross-page state, Pinia would be the right addition.

The one exception is transfer retry state, which is persisted to `localStorage` so that a page refresh after a network failure reuses the same `transaction_id` — making the frontend a participant in idempotency, not just the backend.

> **Note:** Vue 2 was chosen for development speed and familiarity. Vue 2 is EOL (December 2023); Next.js/React would be the greenfield choice per the spec's stated preference.

### Ledger-Based Balance (No Mutable Balance Field)

The most critical design decision: **balance is NEVER stored as a mutable column**. Instead, it is derived from ledger entries by summing credits and debits when needed.

**Why?** This guarantees consistency. A mutable balance column can drift from reality due to bugs, race conditions, or partial updates. The ledger is the single source of truth.

**Trade-off:** Slightly more expensive reads (computed via SUM). For production, a cached balance column updated atomically within the same transfer transaction would restore read performance, with a periodic reconciliation job to detect any drift.

For the account list endpoint, balances are aggregated in the query using `withSum()` rather than recalculated per account record, avoiding an N+1 read pattern.

### Atomic Transfers with Row-Level Locking

```php
DB::transaction(function () {
    // Lock accounts in sorted order → prevents deadlocks
    Account::whereIn('account_id', $sorted)->lockForUpdate()->get();

    // Check balance, create debit + credit entries atomically
});
```

- **Atomicity:** `DB::transaction()` ensures both ledger entries succeed or both are rolled back — there is no code path where a debit commits without its matching credit
- **Deadlock Prevention:** Accounts are always locked in deterministic alphabetical order. Without this, Alice→Bob and Bob→Alice transfers arriving simultaneously could deadlock. Sorted locking ensures both always acquire Alice's lock first, then Bob's — one waits, the other completes cleanly.
- **Race Conditions:** `SELECT ... FOR UPDATE` prevents concurrent transfers from reading the same balance simultaneously and both passing the balance check

### Financial Precision (`bcmath`)

Floating-point arithmetic introduces precision errors that are unacceptable in financial systems (`0.1 + 0.2 = 0.30000000000000004` in PHP). All balance calculations and comparisons use PHP's `bcmath` extension with a fixed scale of 2 decimal places. Amounts are stored as `DECIMAL(15,2)` in MySQL — never `FLOAT`.

**Known trade-off:** Integer pence (storing `1000` for £10.00) would eliminate decimal boundary risk entirely and is the safer production choice. `DECIMAL(15,2)` was chosen here for readability; the arithmetic is still correct via `bcmath`.

### Idempotency (Three Layers)

Three strict layers ensure a duplicate `transaction_id` never results in duplicate transfers, even under concurrent requests:

1. **Pre-lock application check** — Fast query before acquiring any database locks. For obvious replays where the transaction already exists, the service short-circuits immediately without touching locks. This is a performance optimisation for the common retry case.

2. **Inside-transaction check** — After acquiring row-level locks, the service checks again for an existing transfer. This is the correctness layer for same-account-pair races: two concurrent requests with the same `transaction_id` on the same accounts both pass the pre-lock check since neither exists yet. Inside the transaction, under the lock, the loser finds the winner's committed rows and replays correctly.

3. **Database constraint catch** — `UNIQUE(transaction_id, entry_type)` at the schema level handles disjoint-account-pair races. Two concurrent requests with the same `transaction_id` but completely different account pairs won't contend on the same row locks, so layers 1 and 2 won't catch them. Since every transfer produces exactly one debit and one credit, any reuse of a `transaction_id` across different account pairs collides on the debit or credit slot. MySQL throws error `23000`, the transaction rolls back, and the service catches it and returns a clean `409 Conflict`.

If the same `transaction_id` is retried with the same payload, the API returns the original transfer result (200 OK). If retried with a different payload, the API returns `409 Conflict`.

**Known trade-off:** The layer-3 catch relies on `str_contains` matching a MySQL error message string, which is fragile across DB versions. The production-grade solution is a dedicated `transfer_idempotency` table with a `PRIMARY KEY` on `transaction_id` — explicit, version-independent, and easier to reason about.

### Client-Generated Transaction IDs

The frontend generates a UUID v4 for each transfer attempt. If the request fails due to a network error or timeout, the pending transfer metadata (including the `transaction_id`) is preserved in `localStorage` keyed by a transfer fingerprint (`from:to:amount`). On retry with the same fingerprint, the same `transaction_id` is reused. This enables:

- **Safe retries:** If a network error occurs after the server processes the transfer, the client retries with the same ID and receives the original result
- **No double-charge on page refresh:** The idempotency key survives a browser refresh
- **Clean UX for ambiguous failures:** A replayed response on retry is interpreted as "already processed" with an appropriate info notification

---

## 📊 Database Schema

### `accounts`
| Column     | Type         | Notes          |
|------------|--------------|----------------|
| id         | BIGINT PK    | Auto-increment |
| account_id | VARCHAR(10)  | UNIQUE         |
| name       | VARCHAR(100) | Nullable       |
| timestamps |              |                |

### `ledger_entries`
| Column                  | Type          | Notes                          |
|-------------------------|---------------|--------------------------------|
| id                      | BIGINT PK     | Auto-increment                 |
| transaction_id          | VARCHAR(100)  | Idempotency key                |
| account_id              | VARCHAR(10)   | FK → accounts                  |
| entry_type              | ENUM          | 'credit' or 'debit'            |
| amount                  | DECIMAL(15,2) | Always positive, unsigned      |
| counterparty_account_id | VARCHAR(10)   | FK → accounts                  |
| description             | VARCHAR(255)  | Nullable                       |
| created_at              | TIMESTAMP     | Immutable — never updated      |

**Key indexes and constraints:**
- `UNIQUE (transaction_id, entry_type)` — global idempotency guard; since every transfer produces exactly one debit and one credit, this prevents any reuse of a `transaction_id` across any account pair
- `INDEX (account_id, entry_type)` — accelerates balance aggregation queries
- `INDEX (account_id, created_at)` — accelerates transaction history queries ordered by time per account
- Foreign keys on `account_id` and `counterparty_account_id` with `ON DELETE RESTRICT` — prevents deleting accounts that have ledger history

---

## ⚠️ Failure Scenarios & Handling

| Scenario | Handling |
|----------|----------|
| Duplicate `transaction_id` (same payload) | Returns original result — idempotent replay (200 OK) |
| Duplicate `transaction_id` (different payload) | Returns 409 Conflict |
| Insufficient balance | Transfer rejected at service layer (422) |
| Invalid or non-existent account | Rejected at validation layer before service (422) |
| Concurrent transfers on same account | Row-level locking ensures only one proceeds at a time |
| Same `transaction_id` across disjoint account pairs | DB constraint fires (23000), caught and resolved as 409 Conflict |
| Partial failure during transfer | Impossible — both ledger entries are written in a single DB transaction |
| Network drop after server processes transfer | Client retries with same `transaction_id` from localStorage — safe replay |

---

## 🧪 Running Tests

```bash
# Run all tests inside Docker
make test

# Or directly
docker compose exec backend php artisan test tests/Feature
```

### Test Coverage
- **AccountTest:** Create, duplicate rejection, balance derivation, transaction history, 404 handling
- **TransferTest:** Normal transfer, idempotency replay, conflicting duplicate transaction IDs, insufficient funds, negative amounts, zero amounts, oversized amounts, self-transfer, nonexistent accounts, exact balance transfer, sequential transfers, decimal normalisation, input normalisation

---

## 🧪 Manual Testing Tips

### Test Idempotency
1. Send a transfer request
2. Repeat the same request with identical `transaction_id`
3. Observe:
   - First request → `201 Created`, `idempotency_status: "created"`
   - Second request → `200 OK`, `idempotency_status: "replayed"`

### Test Conflict Detection
1. Send a transfer with `transaction_id: "TXN-001"`
2. Repeat with same `transaction_id` but a different `amount`
3. Observe:
   - `409 Conflict` with a validation error on `transaction_id`

### Test Insufficient Balance
1. Attempt to transfer more than the account's current balance
2. Observe:
   - `422 Unprocessable Entity`, no ledger entries created
   - Balance unchanged — confirmed by re-fetching

### Test Self-Transfer Prevention
1. Set `from_account_id` and `to_account_id` to the same account
2. Observe:
   - `422 Unprocessable Entity` with validation error on `to_account_id`

---

## 🐳 Useful Docker Commands

```bash
make up              # Start all services
make down            # Stop all services
make logs            # View all logs
make logs-backend    # View backend logs only
make fresh           # Reset DB (migrate:fresh + seed)
make test            # Run PHPUnit tests
make shell-backend   # Open bash in backend container
make shell-db        # Open MySQL CLI
make clean           # Stop + remove volumes
```

---

## ⚖️ Trade-offs

| Area               | Chose This                                       | Production Alternative                         |
|--------------------|--------------------------------------------------|------------------------------------------------|
| Balance            | Derived from ledger SUM on every read            | Cached column updated atomically + reconciliation job |
| Amount storage     | DECIMAL(15,2)                                    | Integer pence — eliminates all decimal boundary risk |
| Idempotency guard  | DB constraint + 23000 catch                      | Dedicated `transfer_idempotency` table with PRIMARY KEY |
| Auth               | None (confirmed out of scope)                    | JWT / OAuth2, accounts scoped to users         |
| Pagination         | Offset-based, max `per_page` 50                  | Cursor-based — O(1) per page at any depth      |
| State management   | Vue 2 built-in reactivity (single-owner pattern) | Vuex / Pinia for multi-page apps               |
| Transfer processing| Synchronous                                      | Queue-based async for high volume              |
| Error handling     | Structured JSON API envelopes                    | Distributed tracing with correlation IDs       |
| Monitoring         | Structured logs                                  | APM + metrics + alerting (Datadog / New Relic) |

---

## 📁 Project Structure

```
├── docker-compose.yml          # Service orchestration
├── Makefile                    # Convenience commands
├── README.md                   # This file
├── AI_USAGE.md                 # AI usage transparency
├── backend/
│   ├── Dockerfile
│   ├── entrypoint.sh           # DB wait + migrate + seed
│   ├── app/
│   │   ├── Http/Controllers/   # AccountController, TransferController
│   │   ├── Http/Requests/      # Form validation
│   │   ├── Http/Resources/     # API response formatting
│   │   ├── Models/             # Account, LedgerEntry
│   │   ├── Services/           # TransferService (core logic)
│   │   └── Exceptions/         # Custom exception classes
│   ├── database/migrations/    # Schema definitions
│   ├── database/seeders/       # Demo data
│   ├── routes/api.php          # API routing
│   └── tests/Feature/          # PHPUnit tests
└── frontend/
    ├── Dockerfile
    ├── src/
    │   ├── App.vue             # Global shell and styles
    │   ├── views/DashboardView.vue
    │   ├── services/api.js     # Axios API layer
    │   └── components/
    │       ├── AccountSelector.vue
    │       ├── BalanceDisplay.vue
    │       ├── TransferForm.vue
    │       ├── TransactionList.vue
    │       ├── CreateAccountModal.vue
    │       └── NotificationToast.vue
    └── public/index.html
```

---

## 🔮 Scaling Considerations

1. **Materialised balance** — Add a cached balance column updated atomically inside the same transfer DB transaction. Keep the ledger as source of truth but remove the SUM from the hot read path. A background reconciliation job recomputes from the ledger periodically and alerts on any drift.

2. **Dedicated idempotency table** — Replace the `UNIQUE(transaction_id, entry_type)` + error-catch approach with a `transfer_idempotency` table keyed by `transaction_id`. Cleaner, version-independent, and easier to extend.

3. **Cursor-based pagination** — Replace offset pagination with a stable cursor (last seen `id` or `created_at`). Offset pagination degrades linearly on large tables; cursor pagination is O(1) regardless of depth.

4. **Async transfer processing** — Move to a queue-based architecture for high-volume scenarios. Enqueue the transfer immediately, return a pending status, process via workers. Decouples API throughput from transfer latency and allows horizontal scaling of workers independently.

5. **Read replicas** — Balance queries and transaction history don't need the primary DB. Route read traffic to replicas, keeping the primary exclusively for transfer writes.

6. **Authentication and authorisation** — JWT-based auth with accounts scoped to authenticated users. RBAC for any admin operations. Rate limiting per user and per IP.

7. **Observability** — Correlation IDs threaded through all log lines per request. APM integration for latency percentiles and error rate alerting. Specific alerts for: balance drift, high transfer error rate, slow p99 transfer latency, DB connection pool exhaustion.

---

## 🛡️ Production Readiness Gaps

This submission demonstrates correct financial engineering patterns within the exercise scope. The following would be required before production deployment:

| Gap                   | Current State               | Production Fix                                  |
|-----------------------|-----------------------------|-------------------------------------------------|
| Authentication        | None                        | JWT / OAuth2, per-user account scoping          |
| Idempotency table     | DB constraint + error catch | Dedicated `transfer_idempotency` table          |
| Balance caching       | Derived from SUM on reads   | Materialised balance + reconciliation job       |
| Distributed tracing   | Structured logs             | Correlation IDs + APM                           |
| DB statement timeouts | None                        | Per-query timeout to prevent hung transfers     |
| Rate limiting         | None                        | Per-user and per-IP limits                      |
| Multi Currency Support| None                        | Add proper Multi Currency Support               |
| Integer amounts       | DECIMAL(15,2)               | Integer pence for zero decimal risk             |
| Cursor pagination     | Offset-based                | Cursor-based for large ledgers                  |
| Transfer queue        | Synchronous                 | Queue-based async at high volume                |
