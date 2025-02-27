<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface Store
{
    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return array<AggregateChanged<array<string, mixed>>>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array;

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool;

    /**
     * @param class-string<AggregateRoot>                   $aggregate
     * @param array<AggregateChanged<array<string, mixed>>> $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void;
}
