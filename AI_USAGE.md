# AI Usage Disclosure

This project was built with AI assistance. I am disclosing that usage transparently in line with the exercise instructions.

## Tools Used

1. **Google Antigravity**
   - Used initially for requirement analysis, planning, assumptions, and early project structure creation.

2. **ChatGPT Codex**
   - Used afterward for iterative backend/frontend development, review, debugging, validation, UI refinement, and testing support.

## Prompt Log

Below are the prompts I used during the assignment workflow.

### Prompts Used With Google Antigravity

1. `I have cleared the 1st round of my interview at https://hoxtonwealth.com/ now i got an take home exercise which i am attaching I want you to create a development plan to build this using Laravel 12, Vue 2, And Mysql Dont start the development now only analyse the exercise document and create a proper step by step plan`

2. `I think the plans looks good but i forgot to mention we need to use docker also, so add that also in plan`

3. `Ok Plan is good but please mention the questions if you have any and also the assumptions`

4. `I think you missed to mention authentication, authorization, type of roles needed, login and register features. I think we need to ask them as they are not mentioned in the requirement document.`

5. `We don’t need authentication in APIs and also no need of authorization, roles and login/register features, I confirmed it with the team.`

6. `Lets start the project structure creation then we will go one by one and complete all the steps we planned earlier`

### Prompts Used With ChatGPT Codex

7. `I have cleared the 1st round of my interview at https://hoxtonwealth.com/ now i got a take home exercise which i am attaching. I created a project structure and initial features, please review the document then compare it will the development we already did`

8. `Lets start with backend api’s.`

9. `Now I want you to match our ui with https://hoxtonwealth.com/ ui`

10. `Great but it still not looking good`

11. `Now it is looking good but we need to make navbar color match with the https://hoxtonwealth.com/`

12. `Also make the website responsive`

13. `Now lets review the backend. Please be sure to follow proper optimization approaches, api is well written, queries are clean and no n+1 queries try to use eager loading and relations if needed.`

14. `Add validation on account_id, transaction_id, amount, name in all the api’s also on frontend`

15. `Review full project by comparing it with the requirements mentioned in the document`

16. `please make sure transactions are working correctly, also db locking is working and indexes are used ,etc`

17. `Now check feature tests`

## How AI Helped

### Google Antigravity
- Helped analyze the exercise document
- Helped create the initial development plan
- Helped include Docker in the execution plan
- Helped identify assumptions and open questions
- Helped with the first-pass project structure setup

### ChatGPT Codex
- Helped review the current implementation against the requirements
- Helped improve backend correctness, validation, and API design
- Helped improve UI styling and responsiveness
- Helped review data integrity concerns such as transactions, locking, and indexes
- Helped review and improve test coverage
- Helped with refactoring, debugging, deployment guidance, and documentation improvements

## Which Parts Were AI-Assisted

The following parts of the project were AI-assisted at some point:

- initial planning and task breakdown
- Docker setup and local environment workflow
- backend architecture and implementation review
- frontend UI refinement and responsiveness
- validation improvements
- transaction and locking review
- database indexing review
- feature test review and additions
- README and AI usage documentation

## What I Modified / Reviewed Myself

I did not accept AI output blindly. I reviewed and validated the project manually.

My manual review and validation included:

1. Reviewing the architecture against the exercise requirements
2. Confirming with the team that auth, roles, login, and register were out of scope
3. Inspecting the generated and modified code
4. Checking the ledger-based design and transfer workflow
5. Validating transfer atomicity and idempotency behavior
6. Reviewing validation rules across backend and frontend
7. Manually testing important flows:
   - create account
   - fetch balance
   - transfer funds
   - duplicate transaction handling
   - insufficient funds
   - invalid account handling
   - transaction history and pagination
8. Running and fixing feature tests
9. Reviewing UI behavior in desktop and mobile layouts
10. Verifying deployment-related behavior and environment setup

## Summary

AI was used as a planning, development, debugging, and review assistant.

I personally:
- validated the requirements and scope
- reviewed the generated code
- made implementation decisions
- verified the important business logic and edge cases
- tested and refined the final project

Transparency is more important than avoidance, so I am explicitly disclosing that AI meaningfully assisted this submission.
