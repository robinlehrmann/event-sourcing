<?php

namespace Patchlevel\EventSourcing\Projection;

final class ProjectorCriteria
{
    public function __construct(
        public readonly ?array $names = null
    ) {
    }
}