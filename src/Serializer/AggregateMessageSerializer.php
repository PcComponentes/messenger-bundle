<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Serializer;

use Assert\Assert;
use Pccomponentes\Ddd\Domain\Model\ValueObject\DateTimeValueObject;
use Pccomponentes\Ddd\Util\Message\Serialization\Exception\MessageClassNotFoundException;
use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageJsonApiSerializable;
use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageStream;
use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageStreamDeserializer;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

final class AggregateMessageSerializer extends DomainSerializer
{
    private const AGGREGATE_VERSION = 0;

    private AggregateMessageJsonApiSerializable $serializer;
    private AggregateMessageStreamDeserializer $deserializer;

    public function __construct(
        Tracker $tracker,
        AggregateMessageJsonApiSerializable $serializer,
        AggregateMessageStreamDeserializer $deserializer
    ) {
        parent::__construct($tracker);

        $this->serializer = $serializer;
        $this->deserializer = $deserializer;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $message = $this->streamFromEncodedEnvelope($encodedEnvelope);

        try {
            $aggregateMessage = $this->deserializer->unserialize($message);
        } catch (MessageClassNotFoundException $exception) {
            throw new MessageDecodingFailedException();
        }

        $this->obtainDomainTrace($aggregateMessage, $encodedEnvelope);

        return new Envelope($aggregateMessage);
    }

    public function encode(Envelope $envelope): array
    {
        return [
            'body' => $this->serializer->serialize(
                $envelope->getMessage(),
            ),
            'headers' => [
                'Content-Type' => 'application/json',
                'x-correlation-id' => $this->tracker()->correlationId(),
                'x-reply-to' => $this->tracker()->replyTo(),
            ],
        ];
    }

    private function streamFromEncodedEnvelope(array $encodedEnvelope): AggregateMessageStream
    {
        $body = \json_decode($encodedEnvelope['body'], true);

        if (false === \array_key_exists('data', $body)) {
            return $this->streamFromLegacyEncodedEnvelope($encodedEnvelope);
        }

        $this->assertContent($body);
        $aggregateMessage = $body['data'];

        return new AggregateMessageStream(
            $aggregateMessage['message_id'],
            $aggregateMessage['attributes']['aggregate_id'],
            (int) $aggregateMessage['occurred_on'],
            $aggregateMessage['type'],
            self::AGGREGATE_VERSION,
            \json_encode($aggregateMessage['attributes']),
        );
    }

    private function assertContent(array $content): void
    {
        Assert::lazy()->tryAll()
            ->that($content['data'], 'data')->isArray()
            ->keyExists('message_id')
            ->keyExists('type')
            ->keyExists('occurred_on')
            ->keyExists('attributes')
            ->verifyNow()
        ;

        Assert::lazy()->tryAll()
            ->that($content['data']['message_id'], 'message_id')->uuid()
            ->that($content['data']['type'], 'type')->string()->notEmpty()
            ->that($content['data']['occurred_on'], 'occurred_on')->notEmpty()
            ->that($content['data']['attributes'], 'attributes')->isArray()->keyExists('aggregate_id')
            ->verifyNow()
        ;

        Assert::lazy()->tryAll()
            ->that($content['data']['attributes']['aggregate_id'], 'aggregate_id')->uuid()
            ->verifyNow()
        ;
    }

    private function streamFromLegacyEncodedEnvelope(array $encodedEnvelope): AggregateMessageStream
    {
        $aggregateMessage = \json_decode($encodedEnvelope['body'], true);
        $this->assertLegacyContent($aggregateMessage);

        $occurredOn = DateTimeValueObject::from($aggregateMessage['occurred_on']);

        return new AggregateMessageStream(
            $aggregateMessage['message_id'],
            $aggregateMessage['aggregate_id'],
            $occurredOn->getTimestamp(),
            $aggregateMessage['name'],
            self::AGGREGATE_VERSION,
            \json_encode($aggregateMessage['payload']),
        );
    }

    private function assertLegacyContent(array $content): void
    {
        Assert::lazy()->tryAll()
            ->that($content)
            ->keyExists('message_id')
            ->keyExists('aggregate_id')
            ->keyExists('name')
            ->keyExists('payload')
            ->keyExists('occurred_on')
            ->verifyNow()
        ;

        Assert::lazy()->tryAll()
            ->that($content['message_id'], 'message_id')->uuid()
            ->that($content['aggregate_id'], 'aggregate_id')->uuid()
            ->that($content['name'], 'type')->string()->notEmpty()
            ->that($content['payload'], 'payload')->isArray()
            ->that($content['occurred_on'], 'occurred_on')->notEmpty()
            ->verifyNow()
        ;

        Assert::lazy()->tryAll()
            ->verifyNow()
        ;
    }
}
