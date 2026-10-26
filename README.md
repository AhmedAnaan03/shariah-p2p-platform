# Shariah-Compliant P2P Financing & SME Crowdinvesting Platform

Final Year Project (BSc (Hons) Software Engineering, Asia Pacific University of Technology & Innovation)
by Ahmed Anaan Najeeb (TP079406, APD3F2605SE).

A web-based peer-to-peer financing and SME crowd investing platform for the Maldives, with
AI-assisted risk analysis (XGBoost + SHAP explanations) and an optional Shariah-compliant mode
(Murabahah and Mudarabah). Academic prototype: all money movements use a simulated wallet, and
Shariah compliance is rule-based, not a formal certification.

## Repository layout

| Folder | Contents |
|---|---|
| `web/` | Laravel application with React + Tailwind UI, MySQL 8 |
| `risk-service/` | Python/Flask risk-scoring service (scikit-learn, XGBoost, imbalanced-learn, SHAP) |
| `docs/` | Backlog, decisions, API contract, Definition of Done, sprint evidence |

## How work is organised

- Adapted Scrum: five sprints of about 9 days, 26 Oct – 9 Dec 2026, finishing a week before the
  16 Dec 2026 final deadline (one GitHub milestone per sprint).
- Every backlog item is a GitHub issue created from `backlog.json` (see `docs/BACKLOG.md`).
- Board columns: Todo → In Progress → Done (GitHub Projects).
- Branches: `feature/PB-XX-short-name`, merged into `main` by pull request.
- A story is done only when it meets the [Definition of Done](docs/DEFINITION_OF_DONE.md).
- Each sprint leaves an evidence folder in `docs/sprints/` (goal, completed stories, demo
  screenshots, retrospective, burndown).

## Setup

Setup instructions for `web/` and `risk-service/` are added as each part is scaffolded (PB-02, PB-03).
