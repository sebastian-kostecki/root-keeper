---
change_id: tree-add-person-panel
title: Dodawanie osób na drzewie w panelu bocznym i przegląd strony drzewa
status: implementing
created: 2026-10-11
updated: 2026-10-11
archived_at: null
---

## Notes

Widok: resources/js/pages/tree/show.tsx (+ resources/js/components/tree/*). Źródło tokenów: resources/css/app.css (:root/.dark → @theme), komponenty: resources/js/components/ui + resources/js/components/side-panel.tsx; wariant kontraktu: istniejący system projektowy. Cel: przegląd całej strony drzewa oraz dodawanie osób (Dodaj ojca/matkę/rodzica, Dodaj dziecko, Dodaj małżonka ze slotu i z menu związku) w jednym panelu bocznym zamiast modala PickOrCreatePersonDialog (lista osób z drzewa + formularz nowej osoby). Pusty stan drzewa (brak probanta) ma przycisk dodania pierwszej osoby w tym samym panelu; zapisana osoba zostaje probantem. Po zapisie lub wyborze osoby panel przełącza się na podgląd dodanej osoby, drzewo odświeża się obok. Poza zakresem: MarryCoupleDialog i potwierdzenia usuwania zostają modalne; „Dodaj osobę” na /persons (PersonCreateDialog) to osobna zmiana. Do przepisania reguła CLAUDE.md „small pickers (choose a person…) stay modal”.

Przed-audyt (/10x-ui, 2026-10-11) — wstępne zarzuty dla /10x-research:
1. Architektura: dodawanie osób w modalu zasłaniającym drzewo — pick-or-create-person-dialog.tsx:142–146 (FormDialogContent), wejścia: parent-slot-dialog.tsx:53, add-child-dialog.tsx:34, add-spouse-dialog.tsx:51 (slot + menu z union-node.tsx:220).
2. Architektura/reguła: CLAUDE.md „Side panels vs modals” utrwala modal dla wyboru osoby.
3. Architektura/pusty stan: show.tsx:47–71 — brak probanta → tekst „Otwórz osobę…” + link do /persons; ślepa uliczka dla drzewa bez osób.
4. Brakujący współdzielony komponent: panel dodawania na SidePanel + klucz w PANEL_PROPS (lib/side-panel-url.ts:6) albo stan klienta — do rozstrzygnięcia; reużyć PersonCandidateList i PersonFormFields.
5. Brakujące tokeny: family-sheet.tsx:428 `h-[30px]`, union-node.tsx:117 `h-[34px]`.
Skan zakodowanych wartości: 3 trafienia (w tym `ring-[3px]` w card-styles.ts:23 — standard shadcn). Zrzuty: serwer E2E (scripts/e2e-server.sh, port 8100, E2E_USERNAME). Specy tree-slot-{parent,child,spouse}.spec.ts zależą od obecnych dialogów.
