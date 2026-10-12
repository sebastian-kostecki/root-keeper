<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Wdrożenie tokenów z design systemu Root Keeper

- **Plan**: context/changes/design-tokens/plan.md
- **Scope**: Full plan (phases 1–4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Verification run

- greps z planu (`chart-`, `ring-ring/50`, `text-destructive` bez `-foreground`, `Twoim|Twojego|(data nieznana)`, `Password updated|Profile updated`): wszystkie puste ✓
- `npm run check` ✓ · `types:check` ✓ · `test:js` 315/315 ✓ · `build` ✓
- `artisan test tests/Feature/Settings` 12/12 ✓ · `pint --test` ✓
- `screenshots/before` i `after`: po 10 plików ✓; log fokusu w `verdict.md`: pełny kolor `ring`, 3 px ✓
- Agent 1 (drift): 0 DRIFT, 0 MISSING, 0 EXTRA w kodzie; nic z listy „NOT doing” nie tknięte.

## Findings

### F1 — Wariant destructive nadpisuje pierścień fokusu przezroczystym czerwonym

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/ui/button.tsx:15, resources/js/components/ui/badge.tsx:17
- **Detail**: Wariant `destructive` nadal ma `focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40`. Po `cn()` (tailwind-merge) późniejsza klasa wygrywa nad bazowym `focus-visible:ring-ring`, więc przycisk „Usuń” w dialogach ma pierścień o krytyciu 20% (40% w ciemnym) — dokładnie ten problem (WCAG 1.4.11), który cała zmiana miała usunąć. Faza 2 zmieniała tylko `ring-ring/50` i `text-white`, nie ruszyła tych klas.
- **Fix**: Usunąć `focus-visible:ring-destructive/20` i `dark:focus-visible:ring-destructive/40` z obu wariantów; `aria-invalid:ring-destructive/*` zostają.
- **Decision**: FIXED (Fix now) — usunięto `focus-visible:ring-destructive/20` i `dark:…/40` z button.tsx i badge.tsx

### F2 — Nagłówek mobilny używa `sidebar-ring` na jasnym tle

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/app-mobile-header.tsx:16,24
- **Detail**: Przycisk menu i link do drzewa mają `focus-visible:ring-sidebar-ring` + `ring-2` na `bg-background`. `--sidebar-ring` (#86c7a2) daje ok. 1,85:1 na papierowym tle w jasnym motywie (poniżej 3:1) i jest 2 px zamiast 3 px. Stan sprzed zmiany, ale plan (faza 2 #4) kopiuje ten token do `app-sidebar.tsx`, gdzie tło jest ciemne i jest poprawny; nagłówek to inna powierzchnia i wymaga `ring`.
- **Fix**: Zastąpić ręczne klasy stałą `focusRing` (przez `cn()`), jak w pozostałych widokach.
- **Decision**: FIXED (Fix now) — `app-mobile-header.tsx`: przycisk bierze pierścień z `Button`, link używa `focusRing`

### F3 — Miniatury źródeł w ciemnym motywie prawie zlewają się z tłem, a 4.6 odhaczone

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/design-tokens/screenshots/verdict.md (/sources ciemny), resources/js/components/sources/source-thumbnail.tsx (`bg-muted`)
- **Detail**: `verdict.md` sam zaznacza „Odstaje” i „do decyzji użytkownika przed akceptacją”: w ciemnym motywie `--muted` (#202721) jest niemal równe `--background` (#131814), więc linie kartki w miniaturach są słabo widoczne. Mimo to punkt 4.6 („zaakceptowane przez użytkownika”) jest `[x]` w Progress. W diffie nie ma dowodu akceptacji ani poprawki. Plan wyłącza `source-thumbnail.tsx` (bursztyn źródeł), ale efekt uboczny podmiany `--muted` jest skutkiem tej zmiany.
- **Fix A ⭐ Recommended**: Zostawić miniatury do zmiany „źródła (bursztyn)”, dopisać do `verdict.md` jawną akceptację użytkownika z uzasadnieniem i odnośnikiem do tej zmiany.
  - Strength: Zgodne z zakresem planu; problem i tak rozwiąże się razem z tokenami `--source*`, które właśnie tam mają trafić.
  - Tradeoff: Do czasu tamtej zmiany miniatury w ciemnym motywie pozostają słabsze.
  - Confidence: MED — zależy, kiedy powstanie zmiana źródeł.
  - Blind spot: Nie sprawdzono, czy inne miejsca z `bg-muted` na `bg-background` mają ten sam problem.
- **Fix B**: Podnieść `--muted` w `.dark` (lub nadać miniaturze wyraźniejsze tło) w tej zmianie.
  - Strength: Naprawia widoczny regres od razu.
  - Tradeoff: `--muted` jest używane szeroko (kontrast 5,7–6,4:1 dla tekstu na muted); zmiana wartości dotknie wielu widoków i wymaga nowych zrzutów.
  - Confidence: LOW — wpływ na pozostałe widoki niezmierzony.
  - Blind spot: Wartość pochodzi z design systemu; zmiana rozjedzie się z artefaktem.
- **Decision**: FIXED via Fix A — akceptacja zapisana w `screenshots/verdict.md`; miniatury czekają na zmianę „źródła (bursztyn)”

### F4 — Szeryfowe imiona łamią się w środku słowa na wąskich kartach drzewa

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/tree/sheet-person-card.tsx:88
- **Detail**: `font-display` na imieniu (zgodnie z planem) nasila istniejące łamanie na wąskich kartach („Kowalsk / i”, „Wiśniew / ska” — `verdict.md`). Zaznaczone w werdykcie jako do decyzji użytkownika; niezałatwione.
- **Fix**: Zostawić (łamanie istniało w „przed”) i zanotować jako osobny temat przy zmianie linii drzewa, albo dodać `hyphens-auto`/`break-words` w karcie.
- **Decision**: FIXED (Fix now) — `hyphens-auto` na imieniu w `CardBody` (`sheet-person-card.tsx`); do obejrzenia na wąskich kartach

### F5 — Pozostałe pierścienie 2 px poza wzorcem

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/layouts/auth/auth-split-layout.tsx:19, resources/js/components/auth/auth-brand-panel.tsx:38
- **Detail**: Dwa linki logo na ekranach auth mają `focus-visible:ring-2` zamiast 3 px; kolory poprawne. (Poza tym `ui/navigation-menu.tsx` ma `ring-ring/50`, ale plik jest martwy — bez importów.)
- **Fix**: Zastąpić `focusRing` (a w brand-panelu `ring-3` przy `ring-sidebar-ring`).
- **Decision**: FIXED (Fix now) — `auth-split-layout.tsx` używa `focusRing`, `auth-brand-panel.tsx` `ring-3`
