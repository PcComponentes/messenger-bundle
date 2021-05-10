<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Serializer;

use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\Ddd\Util\Message\Message;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
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

    protected function assertCorrelationIdIfAvailable(array $encodedEnvelope): void
    {
        if (false === \array_key_exists('x-correlation-id', $encodedEnvelope['headers'])) {
            return;
        }

        if (null === $encodedEnvelope['headers']['x-correlation-id']) {
            throw new MessageDecodingFailedException('Message has a null value for x-correlation-id header.');
        }
    }

    private function getCorrelationId(array $encodedEnvelope): string
    {
        if (false !== \array_key_exists('x-correlation-id', $encodedEnvelope['headers'])) {
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
