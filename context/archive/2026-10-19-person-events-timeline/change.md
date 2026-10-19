---
change_id: person-events-timeline
title: Oś czasu z wiekiem osoby w zakładce Wydarzenia panelu osoby
status: archived
created: 2026-10-19
updated: 2026-10-19
archived_at: 2026-10-19T14:10:49Z
---

## Notes

Zmiana UI (`/10x-ui`). Cel użytkownika: „panel osoby - wydarzenia - musi to byc linia ze znacznikami czasu gdzie są jakies wydarzenia (chodzi o to, aby to wyglądało ładniej wizualnie) + dodaj ile ta osoba miala lat w chwili tego wydarzenia”.

- **Widok (jeden):** zakładka Wydarzenia w panelu osoby — `resources/js/components/persons/events-tab.tsx` (osadzona w `person-modal.tsx`).
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark` → `@theme`); Design System: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU.
- **Wariant kontraktu:** istniejący system projektowy — rozszerzamy, bez drugiej palety. DS nie ma komponentu osi czasu.
- **Przed-audyt (2026-10-19):** skan zakodowanych wartości = 0 trafień (events-tab, person-panel, person-modal, event-source-status).
- **Do ponownego użycia:** `app/Support/Age.php` (wiek z częściowych dat), `resources/js/lib/age.ts` (`formatAge`), `app/Support/PersonTimeline.php`, `app/Http/Resources/TimelineEntryResource.php`.

Wstępne zarzuty do zweryfikowania w research:
1. `events-tab.tsx:47` — `<ul className="divide-y">` to lista, nie oś czasu; brak wspólnego komponentu osi.
2. `TimelineEntryResource.php:33-43` / `types/person.ts:116` — wpis nie niesie wieku osoby w chwili wydarzenia.
3. `events-tab.tsx:9,26` — pochówek bez daty i „data nieznana”; znacznik bez daty wymaga osobnej formy.
4. `events-tab.tsx:42` — goły `<p>Brak wydarzeń.</p>` jako stan pusty (kandydat do odroczenia).
5. `resources/js/lib/view-tokens.test.ts:16-40` — kontrola tokenów nie obejmuje `events-tab.tsx`.
