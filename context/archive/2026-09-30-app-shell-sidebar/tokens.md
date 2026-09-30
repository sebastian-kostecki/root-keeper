# Tokens — app shell

Source: design canvas https://claude.ai/artifact/EgWsEjc3AE64kvCcXpKAKN (boards „A — stany”, „A — motyw ciemny”). Proposed values, not applied yet; they go into `resources/css/app.css` in the token phase of the plan with a line naming this file.

Contrast figures are estimates from OKLCH lightness (relative luminance ≈ L³); re-measure in the screenshot gate.

| Token | Theme | Now | Proposed | Why |
| --- | --- | --- | --- | --- |
| `--sidebar-accent` | light | `oklch(0.97 0 0)` | `oklch(0.945 0 0)` | Hover and current-page fill visible on `--sidebar` `0.985`. |
| `--sidebar-accent` | dark | `oklch(0.269 0 0)` | `oklch(0.29 0 0)` | Same role on `--sidebar` `0.205`. |
| `--sidebar-ring` | light | `oklch(0.87 0 0)` | `oklch(0.556 0 0)` | Same value as `--ring`; about 4.5:1 on the panel (target 3:1, WCAG 2.2 SC 1.4.11). |
| `--sidebar-ring` | dark | `oklch(0.439 0 0)` | `oklch(0.556 0 0)` | About 3.8:1 on `0.205` (now about 2.3:1). |
| `--sidebar-primary-foreground` | dark | `oklch(0.985 0 0)` | `oklch(0.205 0 0)` | Readable on `--sidebar-primary` `0.985`; removes the `text-white dark:text-black` patch. |

Unchanged and used as they are: `--sidebar`, `--sidebar-foreground`, `--sidebar-primary`, `--sidebar-accent-foreground`, `--sidebar-border`, `--primary` (progress bar), `--muted` (avatar fallback outside the sidebar).

Secondary text on the panel (e-mail under the user name): `text-sidebar-foreground/70`, the opacity the sidebar primitive already uses for group labels. `--muted-foreground` on the accent fill is about 4:1 and fails 4.5:1.

Counters next to „Osoby” and „Źródła”: text colour, regular weight, `tabular-nums`, 12 px — the rule set for counters in `sources-list-views`.

Variant C only („szyna”): the panel takes the dark theme's sidebar values in both themes (`--sidebar: oklch(0.205 0 0)`, `--sidebar-foreground: oklch(0.985 0 0)`, `--sidebar-primary: oklch(0.985 0 0)`, `--sidebar-primary-foreground: oklch(0.205 0 0)`, `--sidebar-accent: oklch(0.29 0 0)`, `--sidebar-border: oklch(0.269 0 0)`).

Brand mark on the canvas is a lettermark placeholder („RK” on `bg-sidebar-primary`). The repo has no Root Keeper logo; the Laravel mark must not stay.
