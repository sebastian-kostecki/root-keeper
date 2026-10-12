---
change_id: sources-places-polish
title: Design system Root Keeper na /sources i /places oraz polskie ustawienia
status: implementing
created: 2026-10-12
updated: 2026-10-12
archived_at: null
---

## Notes

Wdróż design system Root Keeper na /sources i /places: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU
Tokeny są już w kodzie (zmiana design-tokens, zarchiwizowana w context/archive).
Przeczytaj project/implementation.md oraz project/components/{SourceStatus,SourceCard,DataTable,SidePanel,EmptyState,SegmentedToggle}/README.md.
Zakres — wiersze implementation.md dotyczące:
- dowodów w bursztynie: event-source-status.tsx (chip z rodzajem dokumentu, „Potwierdzone:” w sr-only, CircleDashed dla „Bez źródła”), ConfirmedMark (FileText w text-source), source-thumbnail.tsx (source-surface + ImageOff w text-source), stopka sources-gallery.tsx, focusRingOffset w lib/focus-ring.ts i jego użycie w galerii;
- etykiet z rzeczownikiem: panele źródła/miejscowości/parafii, entry-number-dialog, okna usuwania i scalania, legacy-entry-number, buried-people-field („Usuń z listy”);
- układu paneli: locality-panel i parish-panel jak source-panel (zapis w footer, „Scal z…” i „Usuń” w actions nagłówka);
- pustych stanów: „Parafie (3)” / „Miejscowości (3)” jako outline, tytuły „Brak źródeł” / „Brak miejscowości” / „Brak parafii”;
- h3 dla sekcji „Opis” i „Potwierdza” w source-panel, ariaLabel „Rodzaj miejsca” na /places.
Dodatkowo (pozostałość po design-tokens): polskie tytuły w ustawieniach — Head i breadcrumbs w pages/settings/{profile,security,appearance}.tsx („Ustawienia profilu” / „Ustawienia zabezpieczeń” / „Ustawienia wyglądu”) i menu w layouts/settings/layout.tsx („Profil”, „Zabezpieczenia”, „Wygląd”).
Przełącznik spisu na /places zostaje nad wyszukiwarką — to decyzja, nie błąd.
Zaktualizuj testy, które szukają przycisków po starych etykietach („Zapisz”, „Usuń”, „Scal”, „Profile settings”) — to zmiana nazwy, nie zachowania.
