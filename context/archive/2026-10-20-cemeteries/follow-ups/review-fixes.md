# Review follow-ups: cemeteries

- **F7 (impl-review 2026-10-21)** — przy zmianie usuwającej kolumnę `sources.cemetery_locality_id`: pole `cemetery_locality_id` w `app/Http/Resources/SourceResource.php` (linie ~42, ~77) jest wartością wyliczaną z `cemetery.locality_id`, nie kolumną. Klient używa go w `resources/js/lib/sources-index.ts` (~284) i `resources/js/components/sources/source-description-fields.tsx` (~99). Usunięcie kolumny nie powinno ruszać tego pola; ewentualna zmiana nazwy dotyczy zasobu i obu miejsc klienta.
