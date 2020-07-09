<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Middleware;

use PcComponentes\Ddd\Domain\Exception\ExistsException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceivedStamp;

final class ExpectedFlowMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (false === $this->isFromConsumer($envelope)) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (ExistsException|UnrecoverableExceptionInterface $e) {
            return $envelope;
        }
    }

    private function isFromConsumer(Envelope $envelope): bool
    {
        return \count($envelope->all(AmqpReceivedStamp::class)) > 0;
    }
}
