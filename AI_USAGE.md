# AI Usage Disclosure

This document transparently discloses the use of AI tools in building this project, as required by the assessment guidelines.

## AI Tool Used

- **Tool:** Antigravity (AI Coding Assistant)
- **Model:** Claude
- **Usage Type:** Pair programming / code generation

## How AI Was Used

### 1. Development Planning
- **Prompt:** Shared the full assessment requirements and asked for a development plan with Laravel + Vue 2 + MySQL stack
- **AI Output:** Comprehensive implementation plan covering architecture, database schema, API design, and phased execution timeline
- **My Input:** Chose tech stack (Laravel, Vue 2, MySQL), decided on Docker-first approach, reviewed and validated the plan

### 2. Code Generation
- **Prompt:** Asked AI to implement the planned architecture, starting with Docker setup, then backend, then frontend
- **AI Output:** Generated the full codebase including:
  - Docker Compose + Dockerfiles
  - Laravel models, controllers, services, migrations, seeders
  - Vue 2 components and API service layer
  - PHPUnit feature tests
- **My Review/Modifications:**
  - Reviewed all code for correctness
  - Validated database schema design
  - Verified transfer atomicity and idempotency logic
  - Tested edge cases manually
  - Verified Docker configuration works

### 3. Documentation
- **Prompt:** Asked AI to generate README and AI_USAGE.md
- **AI Output:** Generated comprehensive documentation
- **My Input:** Reviewed for accuracy and completeness

## What Was AI-Assisted vs. Manual

| Component | AI-Assisted | Manual Review |
|-----------|-------------|---------------|
| Architecture design | ✅ | ✅ Validated approach |
| Docker setup | ✅ | ✅ Tested locally |
| Database schema | ✅ | ✅ Verified constraints & indexes |
| TransferService | ✅ | ✅ Validated locking & idempotency |
| Controllers | ✅ | ✅ Reviewed logic |
| Vue components | ✅ | ✅ Tested UI behavior |
| Tests | ✅ | ✅ Ran and verified |
| README | ✅ | ✅ Reviewed for accuracy |

## Key Decisions I Made (Not AI)

1. **Tech stack choice:** Laravel + Vue 2 + MySQL (per my expertise)
2. **Docker-first approach:** Decided to containerize everything for easy setup
3. **Architecture decisions:** Validated ledger-based balance, deterministic lock ordering, and double-checked idempotency

## My Review/Validation Steps

1. Read through all generated code line-by-line
2. Verified SQL schema for data integrity constraints
3. Traced the transfer flow for race condition handling
4. Checked that idempotency key has proper UNIQUE constraint at DB level
5. Verified Vue component props/events flow correctly
6. Built and ran the Docker setup locally
7. Manually tested all API endpoints
8. Ran the PHPUnit test suite
9. Tested edge cases: duplicate transactions, insufficient funds, self-transfer
