<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Serializer;

use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\Ddd\Util\Message\Message;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

abstract class DomainSerializer implements SerializerInterface
{
    private Tracker $tracker;

    protected function __construct(Tracker $tracker)
    {
        $this->tracker = $tracker;
    }

    protected function tracker(): Tracker
    {
        return $this->tracker;
    }

    protected function obtainDomainTrace(Message $message, array $encodedEnvelope): void
    {
        $this->tracker->assignCorrelationId(
            $this->getCorrelationId($encodedEnvelope),
            $message->messageId(),
        );

        $replyTo = $this->getReplyTo($encodedEnvelope);

        if (null === $replyTo) {
            return;
        }

        $this->tracker->assignReplyTo(
            $replyTo,
            $message->messageId(),
        );
    }

    protected function extractHeaderRetryCount(array $encodedEnvelope): int
    {
        if (false === \array_key_exists('x-retry-count', $encodedEnvelope['headers'])) {
            return 0;
        }

        return (int) $encodedEnvelope['headers']['x-retry-count'];
    }

    protected function buildRetryCountFromDeathHeader(array $encodedEnvelope): int
    {
        if (false === \array_key_exists('x-death', $encodedEnvelope['headers'])) {
            return 0;
        }

        if (false === \is_array($encodedEnvelope['headers']['x-death'])) {
            return 0;
        }

        if (0 === \count($encodedEnvelope['headers']['x-death'])) {
            return 0;
        }

        $retryCount = 0;
        foreach ($encodedEnvelope['headers']['x-death'] as $death) {
            if (false === \array_key_exists('count', $death)) {
                continue;
            }

            $retryCount = $retryCount + (int) $death['count'];
        }

        return $retryCount;
    }

    protected function extractEnvelopeRetryCount(Envelope $envelope): int
    {
        $retryCountStamp = $envelope->last(RedeliveryStamp::class);

        return null !== $retryCountStamp ? $retryCountStamp->getRetryCount() : 0;
    }

    private function getCorrelationId(array $encodedEnvelope): string
    {
        if (false !== \array_key_exists('x-correlation-id', $encodedEnvelope['headers'])
            && true === \is_string($encodedEnvelope['headers']['x-correlation-id'])
            && true === RamseyUuid::isValid($encodedEnvelope['headers']['x-correlation-id'])
        ) {
            return $encodedEnvelope['headers']['x-correlation-id'];
        }

        return Uuid::v4()->value();
    }

    private function getReplyTo(array $encodedEnvelope): ?string
    {
        if (false === \array_key_exists('x-reply-to', $encodedEnvelope['headers'])) {
            return null;
        }

        return $encodedEnvelope['headers']['x-reply-to'];
    }
}
