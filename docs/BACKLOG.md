# Product Backlog

Web-Based Shariah-Compliant P2P Financing and SME Crowdinvesting Platform with AI-Assisted Risk Analysis.

Derived from IR Section 1.8 (role deliverables) and Table 13 (final user requirements). Source of truth: `backlog.json`; GitHub issues are created from it by `setup_github.py`.

| Sprint | Goal | Items | Points |
|---|---|---|---|
| Sprint 1 | Foundation: repo, stack, data model, accounts, roles, KYC; dataset spike | 12 | 34 |
| Sprint 2 | Campaign lifecycle, marketplace, wallet and pledging; model training pipeline | 9 | 39 |
| Sprint 3 | Risk scoring live with SHAP explanations; Shariah contracts and engine | 10 | 37 |
| Sprint 4 | Disbursement, repayments, pro-rata returns, dashboards, notifications, audit | 10 | 36 |
| Sprint 5 | Hardening: security, responsive/cross-browser, UAT fixes, demo data, release | 9 | 22 |
| **Total** | | **50** | **168** |

## Requirement traceability

| Requirement | Backlog items |
|---|---|
| FR1 | PB-06, PB-07, PB-09, PB-11 |
| FR2 | PB-16 |
| FR3 | PB-17 |
| FR4 | PB-25, PB-26 |
| FR5 | PB-23, PB-26 |
| FR6 | PB-19, PB-20 |
| FR7 | PB-37 |
| FR8 | PB-13, PB-28, PB-29, PB-30 |
| FR9 | PB-31 |
| FR10 | PB-13 |
| FR11 | PB-34, PB-35 |
| FR12 | PB-42 |
| FR13 | PB-40 |
| FR14 | PB-39 |
| NFR1 | PB-07, PB-09, PB-18, PB-41, PB-44 |
| NFR2 | PB-45 |
| NFR3 | PB-17, PB-26, PB-40 |
| NFR4 | PB-41 |
| Obj 2 | PB-21, PB-23 |
| Obj 3 | PB-30 |
| Obj 4 | PB-36 |

## Sprint 1: Foundation: repo, stack, data model, accounts, roles, KYC; dataset spike

### PB-01 Set up monorepo structure and conventions
`chore` · Foundation & accounts · **Must** · 1 pts · traces to IR 3.1.3

As the developer, I want a clean repository layout and branch conventions so that every later story has a stable home.

Acceptance criteria:
- [ ] Folders web/ (Laravel + React), risk-service/ (Flask), docs/ exist with READMEs
- [ ] main is protected; work happens on feature/<PB-id>-short-name branches merged by PR
- [ ] PR template contains the 8-point Definition of Done

### PB-02 Scaffold Laravel + React + Tailwind on MySQL 8
`chore` · Foundation & accounts · **Must** · 3 pts · traces to IR 2.4.1, 2.4.4

As the developer, I want the web application skeleton running locally so that features can be built in vertical slices.

Acceptance criteria:
- [ ] Laravel app boots against a local MySQL 8 database (XAMPP or native)
- [ ] React UI layer with Tailwind renders a placeholder page
- [ ] React integration approach (e.g. Inertia.js or API + SPA) chosen and justified in docs/decisions.md
- [ ] One passing feature test runs with php artisan test

### PB-03 Scaffold Flask risk-scoring service
`chore` · Foundation & accounts · **Must** · 2 pts · traces to IR 2.4.3, Fig 6

As the developer, I want a separate Python service skeleton so that the ML work can progress independently of the web app.

Acceptance criteria:
- [ ] risk-service/ has a Flask app with GET /health returning service and model version
- [ ] requirements.txt pins pandas, numpy, scikit-learn, xgboost, imbalanced-learn, shap, flask
- [ ] pytest runs and passes one test

### PB-04 Continuous integration with GitHub Actions
`chore` · Foundation & accounts · **Must** · 2 pts · traces to IR 3.1.3 DoD

As the developer, I want tests to run on every push so that breakages are caught immediately.

Acceptance criteria:
- [ ] Workflow runs Laravel tests and pytest on push and PR
- [ ] A failing test fails the workflow; status badge shown in README

### PB-05 Core data model and migrations
`chore` · Foundation & accounts · **Must** · 5 pts · traces to IR 1.8, Table 13

As the developer, I want the core tables in place so that campaign, wallet and scoring features share one consistent model.

Acceptance criteria:
- [ ] Migrations for users, roles, borrower_profiles, investor_profiles, kyc_documents, campaigns, pledges, wallet_accounts, wallet_transactions, repayment_schedules, repayments, risk_scores, notifications, audit_logs
- [ ] Foreign keys and NOT NULL constraints match the draft ERD in docs/
- [ ] Every migration rolls back cleanly (php artisan migrate:rollback)

### PB-06 Register an account with a role
`story` · Foundation & accounts · **Must** · 3 pts · traces to FR1

As a borrower or investor, I want to register an account and choose my role so that I can use the platform for my purpose.

Acceptance criteria:
- [ ] Registration form validates name, email (unique), password strength and role
- [ ] New accounts start as 'pending verification'
- [ ] Clear validation messages for every invalid input

### PB-07 Sign in / sign out with role-based access
`story` · Foundation & accounts · **Must** · 3 pts · traces to FR1, NFR1

As a user, I want to sign in securely and only see the portal for my role so that my data and actions are protected.

Acceptance criteria:
- [ ] Successful sign-in redirects to the borrower, investor or admin portal
- [ ] Every route is gated by role; a test proves a borrower cannot open investor or admin routes
- [ ] Repeated failed sign-ins are rate-limited; sign-out ends the session

### PB-08 Forgot, reset and change password
`story` · Foundation & accounts · **Must** · 2 pts · traces to IR 1.8

As a user, I want to recover or change my password so that I'm never locked out of my account.

Acceptance criteria:
- [ ] Reset link is emailed (mail log in dev) and expires
- [ ] Change password requires the current password
- [ ] Unit test cases cover blank, mismatched and weak passwords

### PB-09 Borrower profile with simulated KYC upload
`story` · Borrower / SME portal · **Must** · 5 pts · traces to FR1, NFR1

As a borrower/SME, I want to complete my profile and upload identity and business documents so that I can be verified before raising finance.

Acceptance criteria:
- [ ] Profile captures personal/business details, sector, years operating, declared income/revenue
- [ ] Documents upload to private storage and are only viewable by the owner and admins
- [ ] File type and size are validated

### PB-10 Investor profile and suitability acknowledgement
`story` · Investor portal · **Must** · 2 pts · traces to IR 1.8.2

As an investor, I want to complete my profile and acknowledge the risks so that I understand what I'm investing in.

Acceptance criteria:
- [ ] Investor cannot pledge until the suitability acknowledgement is accepted
- [ ] Acknowledgement text explains capital-at-risk in plain language

### PB-11 Admin verifies registrations and documents
`story` · Administrator portal · **Must** · 3 pts · traces to FR1, IR 1.8.3

As an administrator, I want to review and verify or reject new users and their documents so that only legitimate participants transact.

Acceptance criteria:
- [ ] Admin sees a queue of pending users with their documents
- [ ] Approve / reject with a reason; the user is notified
- [ ] Each decision is written to the audit log

### PB-12 Spike: acquire and explore the lending dataset
`spike` · AI risk-scoring service · **Must** · 3 pts · traces to IR 2.4.3, 1.5

As the developer, I want a documented, explored training dataset so that the risk model is built on a trustworthy base.

Acceptance criteria:
- [ ] Public dataset (e.g. LendingClub) downloaded; source, licence, size and default rate recorded in risk-service/DATA.md
- [ ] Notebook with EDA: class balance, missing values, candidate features
- [ ] Feature list maps to the four IR groups (application, capacity, history, stability); protected attributes and post-outcome fields excluded


## Sprint 2: Campaign lifecycle, marketplace, wallet and pledging; model training pipeline

### PB-13 Create a financing request
`story` · Borrower / SME portal · **Must** · 5 pts · traces to FR10, FR8

As a borrower/SME, I want to create a financing request with amount, purpose, tenure and repayment structure so that investors can fund it.

Acceptance criteria:
- [ ] Purpose options include start-up, growth, education and personal
- [ ] Borrower chooses conventional or Shariah-compliant mode
- [ ] Only verified borrowers can submit; request enters 'submitted' state

### PB-14 Attach supporting financial information to a request
`story` · Borrower / SME portal · **Must** · 2 pts · traces to IR 1.8.1

As a borrower/SME, I want to upload supporting financial information so that my request can be risk-assessed fairly.

Acceptance criteria:
- [ ] Fields used by the scoring model are captured in structured form, not only as files
- [ ] Missing required fields block submission with clear messages

### PB-15 Admin approves, rejects or suspends campaigns
`story` · Administrator portal · **Must** · 3 pts · traces to IR 1.8.3

As an administrator, I want to approve, reject or suspend financing campaigns so that only suitable campaigns reach investors.

Acceptance criteria:
- [ ] Approved campaigns appear in the marketplace with a funding deadline
- [ ] Reject/suspend require a reason and notify the borrower
- [ ] Actions recorded in the audit log

### PB-16 Browse, search and filter the marketplace
`story` · Investor portal · **Must** · 3 pts · traces to FR2

As an investor, I want to browse, search and filter live campaigns so that I can find opportunities that suit me.

Acceptance criteria:
- [ ] Filters: sector, purpose, amount range, tenure, risk grade, Shariah mode
- [ ] Keyword search on campaign title and business name
- [ ] Paginated results; works on phone and desktop

### PB-17 Campaign detail page with disclosures
`story` · Investor portal · **Must** · 3 pts · traces to FR3, NFR3

As an investor, I want a campaign page that discloses the purpose, business background, projected return and repayment history so that I can make an informed decision.

Acceptance criteria:
- [ ] Page shows purpose, business background, amount, tenure, projected return, borrower repayment history, contract type
- [ ] Risk score area reserved (filled by PB-26)

### PB-18 Simulated wallet and ledger
`story` · Wallet & crowd investing · **Must** · 5 pts · traces to IR 1.5, NFR1

As a user, I want a platform wallet with a clear transaction history so that money movements are traceable.

Acceptance criteria:
- [ ] Simulated top-up and withdrawal; balance derived from ledger entries
- [ ] Every money movement is a ledger entry written inside a DB transaction
- [ ] Tests prove balances never go negative and always reconcile with the ledger

### PB-19 Pledge to a campaign from the wallet
`story` · Wallet & crowd investing · **Must** · 5 pts · traces to FR6

As an investor, I want to pledge funds to a campaign from my wallet so that I can take part in collective financing.

Acceptance criteria:
- [ ] Pledge amount is held in escrow, not paid out yet
- [ ] Cannot pledge more than wallet balance or remaining campaign amount
- [ ] Investor can pledge to several campaigns

### PB-20 Funding progress and all-or-nothing settlement
`story` · Wallet & crowd investing · **Must** · 5 pts · traces to FR6

As a borrower or investor, I want to see funding progress and know what happens at the deadline so that outcomes are predictable.

Acceptance criteria:
- [ ] Progress bar and percentage update after each pledge
- [ ] Target met: campaign becomes 'funded'; deadline missed: all escrowed pledges are refunded
- [ ] Scheduled job handles deadlines; covered by tests

### PB-21 Model training pipeline with baselines
`story` · AI risk-scoring service · **Must** · 8 pts · traces to IR 2.4.3, Obj 2

As the developer, I want a reproducible training pipeline comparing three models so that the chosen model is justified by evidence.

Acceptance criteria:
- [ ] Untouched holdout split; stratified k-fold CV on the development set
- [ ] Imbalance handling (class weights / SMOTE) applied inside training folds only
- [ ] Logistic regression, random forest and XGBoost tuned on CV AUC; F1 and confusion matrix reported
- [ ] Results table saved to risk-service/reports/ for Chapter 5


## Sprint 3: Risk scoring live with SHAP explanations; Shariah contracts and engine

### PB-22 Holdout evaluation, grade bands and versioned model
`story` · AI risk-scoring service · **Must** · 3 pts · traces to IR 2.4.3

As the developer, I want the final model evaluated once on the holdout and saved with a version so that every score is traceable.

Acceptance criteria:
- [ ] Holdout AUC/F1/confusion matrix recorded once
- [ ] Probabilities mapped to grades A–E with fixed, documented cut-offs
- [ ] Pipeline, cut-offs and phrase map saved together under a version number

### PB-23 SHAP explanations in plain language
`story` · AI risk-scoring service · **Must** · 5 pts · traces to FR5, Obj 2, IR 2.4.3

As an investor or borrower, I want each risk score explained in plain words so that I can trust and understand it.

Acceptance criteria:
- [ ] Global SHAP summary checked for economic sense and saved for the report
- [ ] Per-application top factors returned with direction (raises / lowers risk) and size
- [ ] Each feature maps to a plain-language phrase; the phrase map is versioned with the model

### PB-24 Scoring REST endpoint
`story` · AI risk-scoring service · **Must** · 3 pts · traces to IR Fig 4, Fig 6

As the web application, I want a scoring endpoint so that every financing request can be scored on demand.

Acceptance criteria:
- [ ] POST /score validates input and returns probability, grade, top factors and model version
- [ ] Invalid input returns 422 with field errors; contract documented in docs/api.md
- [ ] pytest covers success and error cases

### PB-25 Score requests automatically on submission
`story` · AI risk-scoring service · **Must** · 5 pts · traces to FR4

As an administrator, I want every submitted request scored automatically so that no campaign is listed without a risk assessment.

Acceptance criteria:
- [ ] Laravel calls the scoring service on submission and stores score, grade, factors and model version
- [ ] If the service is down the request shows 'score pending' and is retried by a queued job
- [ ] Integration test runs the full REST round trip

### PB-26 Display risk grade and explanation
`story` · Investor portal · **Must** · 3 pts · traces to FR4, FR5, NFR3

As an investor, I want to see each campaign's risk grade and its main reasons so that risk is part of my decision.

Acceptance criteria:
- [ ] Grade badge shown on marketplace cards and the campaign page
- [ ] Top factors listed in plain language with raise/lower indicators
- [ ] Borrower sees the same explanation for their own request

### PB-28 Murabahah contract template
`story` · Shariah compliance engine · **Must** · 5 pts · traces to FR8, IR 2.2.5

As a faith-sensitive borrower, I want to raise finance as a Murabahah (cost-plus sale) so that no interest is involved.

Acceptance criteria:
- [ ] Captures asset cost and a disclosed, fixed markup; no interest-rate field exists
- [ ] Deferred payment schedule derived from cost + markup
- [ ] Markup cannot change after the contract is agreed

### PB-29 Mudarabah contract template
`story` · Shariah compliance engine · **Must** · 5 pts · traces to FR8, IR 2.2.5

As a faith-sensitive investor, I want to invest through a Mudarabah (profit-sharing) so that returns come from real business profit.

Acceptance criteria:
- [ ] Profit-sharing ratio agreed up front; no guaranteed return is shown or stored
- [ ] Loss allocation follows the Mudarabah rule (borne by capital provider except negligence)

### PB-30 Shariah compliance validation engine
`story` · Shariah compliance engine · **Must** · 5 pts · traces to FR8, Obj 3

As a faith-sensitive user, I want the platform to block non-compliant structures so that Shariah mode can be trusted.

Acceptance criteria:
- [ ] A Shariah-mode request with any interest-bearing structure cannot be created (tested)
- [ ] Prohibited purposes / sectors are rejected at creation
- [ ] Checks also run at pledge and repayment time; violations are logged

### PB-31 Shariah-only marketplace mode for investors
`story` · Investor portal · **Must** · 2 pts · traces to FR9

As a faith-sensitive investor, I want to restrict browsing and investing to Shariah-compliant campaigns so that I never invest against my principles by mistake.

Acceptance criteria:
- [ ] Toggle persists per user; non-compliant campaigns hidden and cannot be pledged to while on

### PB-33 Shariah disclaimer labelling
`story` · Shariah compliance engine · **Must** · 1 pts · traces to IR 1.5

As a user, I want it made clear that compliance is rule-based and not a formal certification so that I'm not misled.

Acceptance criteria:
- [ ] Disclaimer shown on Shariah campaigns and in the onboarding content


## Sprint 4: Disbursement, repayments, pro-rata returns, dashboards, notifications, audit

### PB-27 Admin views and annotates risk assessments
`story` · Administrator portal · **Must** · 2 pts · traces to IR 1.8.3

As an administrator, I want to view and annotate a campaign's risk assessment so that human judgement is recorded alongside the model.

Acceptance criteria:
- [ ] Admin note saved with author and time; visible in campaign review

### PB-32 Admin configures Shariah rules
`story` · Administrator portal · **Must** · 3 pts · traces to IR 1.8.3

As an administrator, I want to configure prohibited sectors and allowed contract types so that rules can be updated without code changes.

Acceptance criteria:
- [ ] Rule changes are versioned and audit-logged
- [ ] Rules apply to new requests immediately

### PB-34 Disbursement and repayment schedule generation
`story` · Wallet & crowd investing · **Must** · 5 pts · traces to FR11

As a borrower/SME, I want funds disbursed and a repayment schedule created when my campaign is funded so that I know exactly what I owe and when.

Acceptance criteria:
- [ ] Escrow released to borrower wallet on 'funded'
- [ ] Schedule generated per contract type (conventional, Murabahah, Mudarabah)
- [ ] Ledger reconciles after disbursement

### PB-35 Record repayments with status visibility
`story` · Borrower / SME portal · **Must** · 5 pts · traces to FR11

As a borrower/SME, I want to make repayments from my wallet and see their status so that I stay on track.

Acceptance criteria:
- [ ] Each instalment shows due / paid / late
- [ ] Investors see the same status for campaigns they funded
- [ ] Late instalments flagged automatically

### PB-36 Pro-rata returns distribution
`story` · Wallet & crowd investing · **Must** · 5 pts · traces to Obj 4

As an investor, I want each repayment split in proportion to my contribution so that returns are fair.

Acceptance criteria:
- [ ] Distribution proportional to pledge share; rounding remainder handled deterministically
- [ ] Sum distributed equals amount repaid (tested)
- [ ] Objective 4 test: one campaign funded by several investors, returns allocated proportionally

### PB-37 Investor portfolio dashboard
`story` · Investor portal · **Must** · 5 pts · traces to FR7

As an investor, I want a dashboard of my pledges, funded campaigns and expected vs received returns so that I can track my portfolio.

Acceptance criteria:
- [ ] Totals, per-campaign status and charts (Chart.js or Recharts)
- [ ] Figures reconcile with the wallet ledger

### PB-38 Borrower dashboard
`story` · Borrower / SME portal · **Must** · 3 pts · traces to IR 1.8.1

As a borrower/SME, I want a dashboard of my requests, funded amounts and repayment history so that I can manage my obligations.

Acceptance criteria:
- [ ] Shows active requests, funding progress, next instalment and history

### PB-39 Notifications and alerts
`story` · Foundation & accounts · **Should** · 3 pts · traces to FR14

As a user, I want notifications about campaign status, funding, disbursement and due dates so that I don't miss important events.

Acceptance criteria:
- [ ] In-app notification centre with unread count
- [ ] Email notifications via mail log in development

### PB-41 Audit log of critical actions
`story` · Administrator portal · **Must** · 3 pts · traces to NFR1, NFR4

As an administrator, I want critical actions recorded in an audit log so that the platform is accountable.

Acceptance criteria:
- [ ] Verification, campaign decisions, rule changes and role changes logged with actor, time and details
- [ ] Audit entries are read-only in the UI

### PB-43 Admin manages users and roles
`story` · Administrator portal · **Must** · 2 pts · traces to IR 1.8.3

As an administrator, I want to manage accounts, roles and suspensions so that access stays under control.

Acceptance criteria:
- [ ] Suspend / reactivate users; change roles; all actions audit-logged


## Sprint 5: Hardening: security, responsive/cross-browser, UAT fixes, demo data, release

### PB-40 Platform monitoring and summary reports
`story` · Administrator portal · **Should** · 3 pts · traces to FR13, NFR3, IR 1.8.3

As an administrator, I want platform-wide activity figures and reports so that I can monitor the marketplace.

Acceptance criteria:
- [ ] Totals for users, campaigns, amounts funded, repayments, defaults
- [ ] Report export to CSV

### PB-42 Onboarding and education content
`story` · Foundation & accounts · **Should** · 2 pts · traces to FR12

As a new user unfamiliar with P2P, I want short guides on how it works and its risks so that I can participate with confidence.

Acceptance criteria:
- [ ] Pages on P2P mechanics, risk grades, Murabahah and Mudarabah
- [ ] Linked from registration and the dashboards

### PB-44 Security hardening pass
`chore` · Quality & release · **Must** · 5 pts · traces to NFR1, IR 2.2.7

As the developer, I want a security review before release so that the financial prototype is defensible.

Acceptance criteria:
- [ ] HTTPS in the demo environment, CSRF on all forms, secure session cookies
- [ ] Authorisation test for every route and document download
- [ ] OWASP ZAP baseline scan run and findings fixed or documented

### PB-45 Responsive and cross-browser test pass
`chore` · Quality & release · **Must** · 3 pts · traces to NFR2

As a user on any device, I want the platform to work on my phone and desktop so that I can use it anywhere.

Acceptance criteria:
- [ ] Key pages checked on phone and desktop widths
- [ ] Checked in Chrome, Safari and Firefox; issues fixed

### PB-46 Demo seed data and accounts
`chore` · Quality & release · **Must** · 2 pts · traces to Presentation demo

As the presenter, I want realistic seeded data and one account per role so that screenshots, UAT and the live demo run smoothly.

Acceptance criteria:
- [ ] php artisan db:seed creates campaigns in every state, wallets and repayment history
- [ ] Demo credentials listed in README

### PB-47 UAT round and fixes
`chore` · Quality & release · **Must** · 3 pts · traces to Structure guidelines Ch5

As the developer, I want at least three target users to test the system so that it is validated by real users.

Acceptance criteria:
- [ ] UAT forms completed by at least 3 real target users (borrower/SME, investor, admin-type)
- [ ] Each issue raised becomes an issue and is fixed or justified

### PB-48 Load and accessibility checks
`chore` · Quality & release · **Could** · 2 pts · traces to IR 4.2

As the developer, I want basic load and accessibility results so that non-functional quality is evidenced.

Acceptance criteria:
- [ ] Simple load test on marketplace and scoring endpoint; results recorded
- [ ] Lighthouse accessibility score recorded for key pages

### PB-49 Model transfer plan for Maldivian data
`spike` · AI risk-scoring service · **Could** · 1 pts · traces to IR 4.2

As the developer, I want a documented plan for adapting the model to local data so that the IR's open gap is addressed.

Acceptance criteria:
- [ ] docs/model-transfer.md covers locally collectable features, recalibration and accumulating platform data

### PB-50 Release package and documentation
`chore` · Quality & release · **Must** · 1 pts · traces to Moodle submission

As the developer, I want a clean release with setup instructions so that the source code submission runs for markers.

Acceptance criteria:
- [ ] README covers setup for web/ and risk-service/, model file location and demo accounts
- [ ] Tagged release v1.0; source exported for MR_..._FYP_SOURCE_CODE
