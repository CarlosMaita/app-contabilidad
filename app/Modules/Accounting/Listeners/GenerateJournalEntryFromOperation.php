<?php

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Services\PostExecutionToJournal;
use App\Modules\Operations\Events\OperationExecuted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateJournalEntryFromOperation implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private readonly PostExecutionToJournal $poster,
    ) {}

    public function handle(OperationExecuted $event): void
    {
        $this->poster->post($event->execution);
    }
}
