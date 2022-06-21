<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Middleware;

use PcComponentes\SymfonyMessengerBundle\Bus\AllHandledStampExtractor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class SimpleMessagePublisherMiddleware implements MiddlewareInterface
{
    private MessageBusInterface $messageBroker;
    private AllHandledStampExtractor $extractor;

    public function __construct(MessageBusInterface $messageBroker, AllHandledStampExtractor $extractor)
    {
        $this->messageBroker = $messageBroker;
        $this->extractor = $extractor;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $resultStack = $stack->next()->handle($envelope, $stack);
        $commandsResult = $this->extractor->extract($resultStack);

        if (null === $commandsResult || (\is_countable($commandsResult) && 0 === \count($commandsResult))) {
            return $resultStack;
        }

        foreach ($commandsResult as $commands) {
            if (null === $commands) {
                continue;
            }

            // We can accept both one or an array of commands to be dispatched
            if (false === \is_array($commands)) {
                $commands = [$commands];
            }

            foreach ($commands as $theCommand) {
                if (false === \is_object($theCommand)) {
                    continue;
                }

                $this->messageBroker->dispatch($theCommand);
            }
        }

        return $resultStack;
    }
}
