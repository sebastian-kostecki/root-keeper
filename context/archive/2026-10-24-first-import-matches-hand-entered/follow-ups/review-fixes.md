# Review follow-ups: first-import-matches-hand-entered

Source: `reviews/impl-review.md` (2026-10-24)

## F4: Ręczny przebieg dopasowania z imiennikami (przed pierwszym importem na produkcji)

The manual run of 4.6–4.7 on `test-pack` imported all 7 people as new, because the dev tree has no namesakes. It did not exercise the sure table, the doubtful comparisons, the option label length (`Str::limit(74)`), the conflicts, or the preservation of app data on a matched person.

Steps:

1. `vendor/bin/sail artisan backup:run --only-db`
2. In the app, add 2–3 namesakes from `test-pack` to the dev tree:
   - one who is sure through both parents by name, with a photo, a source with an entry number, and a birth place;
   - one with the first name and surname only, which should come out as doubtful;
   - one with a different birth date (month or day), which should give a conflict.
3. `vendor/bin/sail artisan import:research <e-mail> /var/www/html/storage/gen-tree-import/test-pack`. Check:
   - that the tables are readable;
   - that the labels are not cut off;
   - the verdicts;
   - the conflict questions.
   
   Answer "nie".
4. Run it again, answering "tak". The matched person keeps the photo, the source, the place and the note with the block appended.
5. `vendor/bin/sail artisan backup:restore "Root Keeper/<archive>.zip"`
