<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Middleware;

use PcComponentes\Ddd\Domain\Model\DomainEvent;
use PcComponentes\Ddd\Infrastructure\Repository\EventStoreRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class EventRecorderMiddleware implements MiddlewareInterface
{
    private EventStoreRepository $eventStore;

    public function __construct(EventStoreRepository $eventStore)
    {
        $this->eventStore = $eventStore;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof DomainEvent) {
            $this->eventStore->add($message);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
