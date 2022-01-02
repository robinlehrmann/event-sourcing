<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AttributeApplyMethod;
use Patchlevel\EventSourcing\Attribute\Apply;

final class ProfileWithAttributeApply extends AggregateRoot
{
    use AttributeApplyMethod;

    private ProfileId $id;
    private Email $email;
    private int $visited = 0;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function visited(): int
    {
        return $this->visited;
    }

    public static function createProfile(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->record(ProfileCreated::raise($id, $email));

        return $self;
    }

    public function publishMessage(Message $message): void
    {
        $this->record(MessagePublished::raise(
            $this->id,
            $message,
        ));
    }

    public function visitProfile(ProfileId $profileId): void
    {
        $this->record(ProfileVisited::raise($this->id, $profileId));
    }

    #[Apply(ProfileCreated::class)]
    #[Apply(ProfileVisited::class)]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
        if ($event instanceof ProfileCreated) {
            $this->id = $event->profileId();
            $this->email = $event->email();

            return;
        }

        $this->visited++;
    }

    public function aggregateRootId(): string
    {
        return $this->id->toString();
    }
}
