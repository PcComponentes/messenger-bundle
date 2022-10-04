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

    private array $mainDistributedTracingHeaders = [];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if (false === $message instanceof Message || false === \class_exists('\Elastic\Apm\ElasticApm')) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->loadMainDistributedTracingHeaders();

        $transaction = $this->createApmTransaction($message::messageName());

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $exception) {
            $this->addTransactionError($transaction, $exception);
            throw $exception;
        } finally {
            $transaction->end();
        }

        return $envelope;
    }

    private function loadMainDistributedTracingHeaders(): void
    {
        $currentTransaction = \Elastic\Apm\ElasticApm::getCurrentTransaction();

        $parentDistributedTracingHeaders = [];
        $currentTransaction->injectDistributedTracingHeaders(
            static function (string $headerName, string $headerValue) use (&$parentDistributedTracingHeaders): void {
                $parentDistributedTracingHeaders[$headerName] = $headerValue;
            }
        );

        $this->mainDistributedTracingHeaders = \array_merge(
            $this->mainDistributedTracingHeaders,
            $parentDistributedTracingHeaders,
        );
    }

    private function createApmTransaction($messageName)
    {
        return \Elastic\Apm\ElasticApm::newTransaction($messageName, self::ELASTIC_APM_MESSAGE_TYPE)
            ->distributedTracingHeaderExtractor(
                fn (string $headerName) => $this->mainDistributedTracingHeaders[$headerName] ?? null,
            )
            ->asCurrent()
            ->begin();
    }

    private static function addTransactionError(
        \Elastic\Apm\TransactionInterface $transaction,
        \Throwable $exception
    ): void {
        $error = new \Elastic\Apm\CustomErrorData();
        $error->message = $exception->getMessage();
        $error->code = $exception->getCode();

        $transaction->createCustomError($error);
    }
}
