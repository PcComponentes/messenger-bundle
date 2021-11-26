<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Middleware;

use PcComponentes\Ddd\Util\Message\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class ApmMessengerMiddleware implements MiddlewareInterface
{
    private const ELASTIC_APM_MESSAGE_TYPE = 'message';

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if (false === $message instanceof Message || false === \class_exists('\Elastic\Apm\ElasticApm')) {
            return $stack->next()->handle($envelope, $stack);
        }

        $currentTransaction = \Elastic\Apm\ElasticApm::getCurrentTransaction();

        $parentDistributedTracingHeaders = [];
        $currentTransaction->injectDistributedTracingHeaders(
            function (string $headerName, string $headerValue) use (&$parentDistributedTracingHeaders): void {
                $parentDistributedTracingHeaders[$headerName] = $headerValue;
            }
        );

        $transaction = \Elastic\Apm\ElasticApm::newTransaction($message::messageName(), self::ELASTIC_APM_MESSAGE_TYPE)
            ->distributedTracingHeaderExtractor(
                function (string $headerName) use ($parentDistributedTracingHeaders): ?string {
                    return \array_key_exists($headerName, $parentDistributedTracingHeaders)
                        ? $parentDistributedTracingHeaders[$headerName]
                        : null;
                }
            )
            ->asCurrent()
            ->begin();

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } finally {
            $transaction->end();
        }

        return $envelope;
    }
}
