# Processor

The `processor` is a kind of [event bus](./event_bus.md) listener that can execute actions on certain events.
A process can be for example used to send an email when a profile has been created:

```php
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

final class SendEmailListener implements Listener
{
    private Mailer $mailer;

    private function __construct(Mailer $mailer) 
    {
        $this->mailer = $mailer;
    }

    public function __invoke(AggregateChanged $event): void
    {
        if (!$event instanceof ProfileCreated) {
            return;
        }

        $this->mailer->send(
            $event->email(),
            'Profile created',
            '...'
        );
    }
}
```

> :warning: If you only want to listen to certain events, then you have to check it in the `__invoke` method.
