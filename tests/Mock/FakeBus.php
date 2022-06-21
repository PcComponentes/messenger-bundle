<?php declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Mock;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class FakeBus implements MessageBusInterface
{
    /** @var array<Envelope> */
    private array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->messages[] = $envelope;

        return $envelope;
    }

    /**
     * @return array<Envelope>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
