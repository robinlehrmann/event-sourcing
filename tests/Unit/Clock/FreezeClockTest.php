<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Clock;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\FreezeClock;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Clock\FreezeClock */
class FreezeClockTest extends TestCase
{
    public function testCreateDateTimeImmutableWithFrozenClock(): void
    {
        $current = new DateTimeImmutable();
        $clock = new FreezeClock($current);

        $new = $clock->createDateTimeImmutable();

        self::assertSame($current, $new);
    }

    public function testSleep(): void
    {
        $date1 = new DateTimeImmutable();
        $clock = new FreezeClock($date1);
        $clock->sleep(1);
        $date2 = $clock->createDateTimeImmutable();

        $diff = $date1->diff($date2);

        self::assertSame(1, $diff->s);
    }

    public function testReFreeze(): void
    {
        $date1 = new DateTimeImmutable();
        $clock = new FreezeClock($date1);
        $new1 = $clock->createDateTimeImmutable();

        $date2 = new DateTimeImmutable();
        $clock->freeze($date2);
        $new2 = $clock->createDateTimeImmutable();

        self::assertSame($date1, $new1);
        self::assertSame($date2, $new2);
        self::assertNotSame($new1, $new2);
    }
}
