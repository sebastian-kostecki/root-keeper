# Test Enforcement

- A test is required when a change touches: a new endpoint (happy path + the other user's 404, see "Backend conventions for tree-owned data"), a migration that moves data (`tests/Feature/<Name>MigrationTest.php`), the research import (a second user with overlapping data), or a view file scanned by `resources/js/lib/view-tokens.test.ts`.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- Run the affected tests after each change and make sure they pass. Test the changed behavior and its failure modes, nothing beyond them.
- Read the `testing-best-practices` skill before writing tests.
