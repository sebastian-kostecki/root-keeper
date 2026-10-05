<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $source_id
 * @property string|null $entry_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventSource extends Pivot {}
