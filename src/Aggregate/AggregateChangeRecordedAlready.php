<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class AggregateChangeRecordedAlready extends AggregateException
{
    public function __construct()
    {
        parent::__construct('The playhead was already set: event is already recorded.');
    }
}
