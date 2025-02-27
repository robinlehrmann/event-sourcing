<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use InvalidArgumentException;

use function gettype;
use function sprintf;

final class InvalidArgumentGiven extends InvalidArgumentException
{
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct($value, string $need)
    {
        parent::__construct(
            sprintf(
                'Invalid argument given: need type "%s" got "%s"',
                $need,
                gettype($value)
            )
        );

        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }
}
