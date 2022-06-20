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
        $commandsToBeDispatched = $this->extractor->extract($resultStack);

        if (null === $commandsToBeDispatched || (\is_countable($commandsToBeDispatched) && 0 === \count($commandsToBeDispatched))) {
            return $resultStack;
        }

        foreach ($commandsToBeDispatched as $command) {
            if (null === $command) {
                continue;
            }

            // We can accept an array of commands to be executed
            if (true === \is_array($command)) {
                foreach ($command as $singleCommand) {
                    if (null === $singleCommand) {
                        continue;
                    }

                    $this->messageBroker->dispatch($singleCommand);
                }
            } else {
                $this->messageBroker->dispatch($command);
            }
        }

        return $resultStack;
    }
}
