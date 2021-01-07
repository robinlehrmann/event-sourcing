<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use RuntimeException;

final class SendEmailProcessor implements Listener
{
    public function __invoke(AggregateChanged $event): void
    {
        if (!$event instanceof ProfileCreated) {
            throw new RuntimeException();
        }

        var_dump('send email yo');
    }
}
