---
change_id: auth-entry
title: Wejście dla niezalogowanych — logowanie jako strona startowa (layout split)
status: implementing
created: 2026-09-30
updated: 2026-09-30
archived_at: null
---

## Notes

— Widok: layout auth (`resources/js/layouts/auth-layout.tsx` → wariant split) z ekranem logowania jako widokiem wzorcowym; rejestracja, weryfikacja e-mail, 2FA i potwierdzenie hasła dziedziczą layout. Źródło tokenów: istniejące tokeny w `resources/css/app.css` (Tailwind v4 `@theme` + shadcn), marka „Root Keeper” / znak „RK” z `app-shell-sidebar`.

Decyzje (2026-09-30): landing = logowanie — `/` dla niezalogowanego prowadzi do logowania, zalogowany trafia do aplikacji; starterowa strona `welcome.tsx` znika. Reset hasła to osobna zmiana (funkcja: Fortify `resetPasswords`, mail, strony, testy) — tu tylko miejsce na link. Przed planem kanwa z 2–3 wariantami (stany, motyw ciemny, mobile), jak przy shellu.

Kanwa: https://claude.ai/artifact/H3Q5s74TyFYEEftERDRr1w (A — split z panelem marki, B — karta na tle, C — hasło + formularz; dla A: rejestracja, stany, weryfikacja e-mail, motyw ciemny, mobile). Wybrany wariant A (2026-09-30). Zarzuty: audit.md, stan obecny: screenshots/before/.
