# Tokens for variant C (charge C1)

Source: Design canvas „Drzewo — arkusz rodziny”, board „C — karty w kolorze płci”
(https://claude.ai/artifact/NUAmTZj36Pyx1aMj19mt3F, 2026-09-29). Hues are the existing
`--gender-male` (250) / `--gender-female` (5) from `resources/css/app.css` (Ancestris convention,
`context/archive/2026-09-28-graphical-tree-view/research.md` §3); only lightness/chroma were chosen
for surfaces. Contrast figures are estimates from OKLCH lightness (Y ≈ L³), to be confirmed in the
screenshot gate.

| Role | `:root` (light) | `.dark` | Used for | Contrast note |
| --- | --- | --- | --- | --- |
| `--gender-male-surface` | `oklch(0.965 0.02 250)` | `oklch(0.25 0.04 250)` | card fill, male | text `--foreground` ≫ 7:1 both themes |
| `--gender-male-border` | `oklch(0.86 0.06 250)` | `oklch(0.4 0.08 250)` | card border, male | border vs page bg ≈ 1.4:1 light / 2:1 dark — decorative; fill+hue carry the meaning, focus uses `--ring` |
| `--gender-female-surface` | `oklch(0.965 0.02 5)` | `oklch(0.25 0.04 5)` | card fill, female | as male |
| `--gender-female-border` | `oklch(0.87 0.06 5)` | `oklch(0.42 0.08 5)` | card border, female | as male |
| `--tree-line` | `oklch(0.8 0 0)` | `oklch(0.42 0 0)` | connectors, couple line, dashed placeholders, unknown-gender card border | ≈ 1.9:1 light / ≈ 2.4:1 dark vs bg (was 1.2:1 with `--border`). Lines are grouping, not controls — no 3:1 requirement; darker reads as "chart", not UI chrome. |
| `--tree-meta` | `oklch(0.48 0 0)` | `oklch(0.75 0 0)` | lifespans + eye icon on tinted cards | ≈ 5.9:1 on male/female surface light, ≈ 7:1 dark (vs `--muted-foreground` ≈ 4.3:1 on tint) |

Existing roles reused (no change): `--gender-*` (hover border on cards), `--primary` (focus-person
border, open node), `--ring` (keyboard focus halo), `--muted` (static unknown placeholder),
`--card` (unknown-gender fill).

Publish through `@theme` as `--color-gender-male-surface`, … `--color-tree-line`, `--color-tree-meta`,
next to the existing `--color-gender-*` block, with a one-line source comment pointing here.
