# 🏦 Hoxton Mini Wallet

A minimal full-stack financial system that simulates account management, money transfers, transaction tracking, and balance visibility — built with **Laravel 12**, **Vue 2**, and **MySQL 8**.

## Tech Stack

| Layer    | Technology          |
|----------|---------------------|
| Backend  | Laravel 12 (PHP 8.2) |
| Frontend | Vue 2.7 + Axios     |
| Database | MySQL 8.0           |
| DevOps   | Docker Compose      |

---

## 🚀 Quick Start (Docker)

### Prerequisites
- [Docker](https://www.docker.com/get-started) & Docker Compose installed

### Setup & Run

```bash
# 1. Clone the repository
git clone https://github.com/YOUR_USERNAME/hoxton-mini-wallet.git
cd hoxton-mini-wallet

# 2. Build and start all services
docker-compose up --build

# Or use the Makefile
make build
make up
```

### Access the Application

| Service   | URL                          |
|-----------|------------------------------|
| Frontend  | http://localhost:8080         |
| Backend API | http://localhost:8000/api   |
| MySQL     | localhost:3306               |

> **Note:** The first startup takes ~60 seconds as it builds containers, installs dependencies, runs migrations, and seeds the database with 3 demo accounts.

### Default Seeded Accounts

| Account ID | Name          | Initial Balance |
|-----------|---------------|-----------------|
| ACC001    | Alice Johnson | $10,000.00      |
| ACC002    | Bob Smith     | $5,000.00       |
| ACC003    | Charlie Brown | $7,500.00       |

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
**Response 201:** Created account object  
**Response 422:** Validation errors (duplicate ID, invalid format)

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

- `App.vue` provides the global shell/styles
- `views/DashboardView.vue` owns page-level state and orchestration
- `components/*` remain focused on individual UI concerns
- `services/api.js` isolates HTTP concerns from presentation logic

This keeps the product small, but avoids turning the root component into an unstructured monolith.

### Ledger-Based Balance (No Mutable Balance Field)

The most critical design decision: **balance is NEVER stored as a mutable column**. Instead, it's derived on-the-fly from the sum of ledger entries:

```sql
SELECT COALESCE(SUM(CASE 
    WHEN entry_type = 'credit' THEN amount 
    WHEN entry_type = 'debit' THEN -amount 
END), 0) as balance
FROM ledger_entries 
WHERE account_id = ?
```

**Why?** This guarantees consistency. A mutable balance column can drift from reality due to bugs, race conditions, or partial updates. The ledger is the single source of truth.

**Trade-off:** Slightly more expensive reads (computed via SUM). For production, I'd add a cached balance column updated atomically within the transaction, with periodic reconciliation.

For the account list endpoint, balances are aggregated in the query rather than recalculated per account record to avoid an N+1 read pattern.

### Atomic Transfers with Row-Level Locking

```php
DB::transaction(function () {
    // Lock accounts in sorted order → prevents deadlocks
    Account::whereIn('account_id', $sorted)->lockForUpdate()->get();
    
    // Check balance, create debit + credit entries
});
```

- **Atomicity:** `DB::transaction()` ensures both ledger entries succeed or both are rolled back
- **Deadlock Prevention:** Accounts are always locked in alphabetical order
- **Race Conditions:** `SELECT ... FOR UPDATE` prevents concurrent balance reads during transfer

### Idempotency (Double-Checked)

```
1. Quick check BEFORE acquiring locks (optimization)
2. Check AGAIN INSIDE the transaction (correctness)
3. UNIQUE index on (transaction_id, account_id) (last resort)
```

Three layers ensure a duplicate `transaction_id` never creates duplicate transfers, even under concurrent requests. If the same request is retried with the same `transaction_id`, the API returns the original transfer result. If the same `transaction_id` is reused with a different payload, the API returns `409 Conflict`.

### Client-Generated Transaction IDs

The frontend generates UUID v4 for each transfer attempt. If the request fails due to a network error or timeout, the pending transfer metadata is preserved in local storage and reused on retry. This enables:
- **Safe retries:** If a network error occurs after the server processes, the client can retry with the same ID
- **No server-side ID generation race:** The client owns the uniqueness
- **Cleaner UX for ambiguous failures:** A duplicate response after retry can be interpreted as "already processed" rather than accidentally creating a second transfer

---

## 📊 Database Schema

### `accounts`
| Column     | Type         | Notes           |
|-----------|--------------|-----------------|
| id        | BIGINT PK    | Auto-increment  |
| account_id| VARCHAR(50)  | UNIQUE, indexed |
| name      | VARCHAR(100) | Nullable        |
| timestamps|              |                 |

### `ledger_entries`
| Column                  | Type          | Notes                          |
|------------------------|---------------|--------------------------------|
| id                     | BIGINT PK     | Auto-increment                 |
| transaction_id         | VARCHAR(100)  | Idempotency key                |
| account_id             | VARCHAR(50)   | FK → accounts                 |
| entry_type             | ENUM          | 'credit' or 'debit'          |
| amount                 | DECIMAL(15,2) | Always positive, unsigned      |
| counterparty_account_id| VARCHAR(50)   | FK → accounts                 |
| description            | VARCHAR(255)  | Nullable                       |
| created_at             | TIMESTAMP     |                                |

**Key Index:** `UNIQUE (transaction_id, account_id)` — enforces idempotency

---

## 🧪 Running Tests

```bash
# Run all tests inside Docker
make test

# Or directly
docker-compose exec backend php artisan test
```

### Test Coverage
- **AccountTest:** Create, duplicate rejection, balance derivation, transaction history, 404 handling
- **TransferTest:** Normal transfer, idempotency, insufficient funds, negative amounts, zero amounts, self-transfer, nonexistent accounts, exact balance transfer, sequential transfers

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

| Area | Simple (Chose This) | Production Alternative |
|------|---------------------|----------------------|
| Balance | Computed from SUM() | Cached column + reconciliation |
| Auth | None | JWT / OAuth2 |
| Pagination | Limit 100 | Cursor-based pagination |
| State management | Vue.observable | Vuex / Pinia |
| Error handling | HTTP error codes | Structured error envelopes |
| Transfer processing | Synchronous | Queue-based async |
| Monitoring | Logs | APM + metrics + alerting |

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
    │   ├── App.vue             # Main layout + state
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

1. **Balance caching** — Add `balance` column, update atomically within transfer transaction, reconcile hourly
2. **Connection pooling** — Use persistent connections or a connection pooler
3. **Sharding** — Partition by account_id for horizontal scaling (cross-shard = distributed tx)
4. **CQRS** — Separate read/write models; ledger entries are already an event log
5. **Async processing** — Queue-based transfers for high throughput
6. **Rate limiting** — Protect transfer endpoint from abuse
7. **Multi-currency** — Add currency column + exchange rate service
