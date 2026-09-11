<?php

namespace App\Modules\Accounting\Events;

use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;

class JournalEntryPosted
{
    use Dispatchable;

    public function __construct(
        public readonly JournalEntry $entry,
    ) {}
}
