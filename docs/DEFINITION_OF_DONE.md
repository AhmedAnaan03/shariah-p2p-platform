# Definition of Done

A backlog item counts as done only when every point below is true (IR Section 3.1.3).

1. Code is committed to a feature branch and merged into `main` through a pull request.
2. Database migrations run as intended and roll back cleanly.
3. Server-side validation covers every input of the user story.
4. Role-based permissions for the story's routes and actions are checked by a test.
5. All automated tests pass (CI is green).
6. Integration tests for the full REST round trip pass, where applicable.
7. The acceptance criteria can be demonstrated in the running application.
8. Developer documentation for the story is updated.
