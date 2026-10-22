<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A typed birth surname becomes the only surname. The surname that was there before is
     * appended to the note so the author can review it. `maiden_name` is left untouched.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('people')
                ->whereNotNull('maiden_name')
                ->where('maiden_name', '!=', '')
                ->chunkById(200, function ($people): void {
                    foreach ($people as $person) {
                        if (filled($person->last_name) && mb_strtolower($person->last_name) === mb_strtolower($person->maiden_name)) {
                            continue;
                        }

                        $update = ['last_name' => $person->maiden_name];

                        if (filled($person->last_name)) {
                            $label = in_array($person->gender, ['female', null], true)
                                ? 'Nazwisko po mężu'
                                : 'Wcześniej zapisane nazwisko';
                            $line = "{$label}: {$person->last_name}";

                            $update['note'] = filled($person->note) ? $person->note."\n\n".$line : $line;
                        }

                        DB::table('people')->where('id', $person->id)->update($update);
                    }
                });
        });
    }

    /**
     * Reverse the migrations. Not reversible: the former surname now lives in a note the author
     * may have edited, so it cannot be restored reliably. Restore from the `backup:run --only-db`
     * copy that `scripts/deploy.sh` takes before `migrate`.
     */
    public function down(): void
    {
        //
    }
};
