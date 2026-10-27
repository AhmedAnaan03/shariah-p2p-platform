# Working conventions

These conventions apply to every backlog item (PB-XX) in this repository.

## Branches

- `main` always holds working, tested code. Nothing is committed to it directly.
- One branch per backlog item, named `feature/PB-XX-short-name`
  (for example `feature/PB-06-registration`).
- Bug fixes found during testing or UAT use `fix/<issue-number>-short-name`.
- Delete the branch after its pull request is merged.

## Commits

- Small, focused commits written in the imperative: `Add borrower registration form`.
- Start each message with the backlog id: `PB-06: Add borrower registration form`.

## Pull requests

- Every branch is merged into `main` through a pull request.
- The PR description starts with `Closes #<issue>` so the issue closes and moves to Done.
- Every box in the Definition of Done checklist must be ticked before merging
  (see `docs/DEFINITION_OF_DONE.md`).
- CI must pass before merging (from PB-04 onwards).
- Merge with "Squash and merge" so each backlog item is one commit on `main`.

## Board

- Move the issue to **In Progress** when work starts.
- It moves to **Done** automatically when the PR merges and closes the issue.

## Sprint evidence

At the end of each sprint, copy `docs/sprints/SPRINT_TEMPLATE.md` into
`docs/sprints/sprint-N/README.md` and fill it in: goal, planned vs completed points,
demo screenshots, supervisor feedback and retrospective.
