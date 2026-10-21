<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations. A burial without a cemetery first gets the one cemetery of the gravestones its person is
     * listed on in the burial's locality, when that is exactly one. Every other burial with a locality then gets the
     * cemetery without a name in its locality (an existing one is reused), so that the burial place is always a
     * cemetery. Burials without a locality and burials that already have a cemetery stay as they are.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE events
            SET cemetery_id = matched.cemetery_id
            FROM (
                SELECT burials.id AS event_id, MIN(sources.cemetery_id) AS cemetery_id
                FROM events AS burials
                JOIN event_participants ON event_participants.event_id = burials.id AND event_participants.role = ?
                JOIN buried_people ON buried_people.person_id = event_participants.person_id
                JOIN sources ON sources.id = buried_people.source_id
                JOIN cemeteries ON cemeteries.id = sources.cemetery_id AND cemeteries.locality_id = burials.locality_id
                WHERE burials.type = ? AND burials.cemetery_id IS NULL AND burials.locality_id IS NOT NULL
                GROUP BY burials.id
                HAVING COUNT(DISTINCT sources.cemetery_id) = 1
            ) AS matched
            WHERE events.id = matched.event_id
            SQL, [ParticipantRole::Subject->value, EventType::Burial->value]);

        $pairs = DB::table('events')
            ->where('type', EventType::Burial->value)
            ->whereNull('cemetery_id')
            ->whereNotNull('locality_id')
            ->distinct()
            ->orderBy('tree_id')
            ->orderBy('locality_id')
            ->get(['tree_id', 'locality_id']);

        foreach ($pairs as $pair) {
            $cemeteryId = DB::table('cemeteries')
                ->where('tree_id', $pair->tree_id)
                ->where('locality_id', $pair->locality_id)
                ->whereNull('name')
                ->value('id');

            $cemeteryId ??= DB::table('cemeteries')->insertGetId([
                'tree_id' => $pair->tree_id,
                'locality_id' => $pair->locality_id,
                'name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('events')
                ->where('type', EventType::Burial->value)
                ->whereNull('cemetery_id')
                ->where('tree_id', $pair->tree_id)
                ->where('locality_id', $pair->locality_id)
                ->update(['cemetery_id' => $cemeteryId]);
        }
    }

    /**
     * Reverse the migrations. Nothing to undo: the assignment only adds values, entries it made cannot be told
     * from cemeteries entered by hand afterwards, and the column itself is dropped by rolling back the migration
     * that created it.
     */
    public function down(): void
    {
        //
    }
};
