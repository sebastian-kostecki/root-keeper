# Laravel Boost Guidelines

## Skills Activation
This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions
- Backend code follows the write and read paths in "Backend conventions for tree-owned data" (Form Request → Action with `handle()` → `Inertia::flash`; API Resource with `->resolve()`). When creating a file, copy the structure and naming of its sibling files.
- Before finishing a change that touches PHP, run `vendor/bin/sail bin pint --dirty --format agent` and `vendor/bin/sail composer types:check` (Larastan level 7) with zero new errors. For frontend files run `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval. New dependencies follow "Production dependencies".

## Frontend Bundling
- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.
