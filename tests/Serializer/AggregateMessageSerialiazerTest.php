<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Serializer;

use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageJsonApiSerializable;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageStreamDeserializer;
use PcComponentes\Ddd\Util\Message\Serialization\MessageMappingRegistry;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use PcComponentes\SymfonyMessengerBundle\Serializer\AggregateMessageSerializer;
use PcComponentes\SymfonyMessengerBundle\Tests\Mock\EventMock;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class AggregateMessageSerialiazerTest extends TestCase
{
    private Tracker $tracker;
    private AggregateMessageSerializer $serializer;

    public function setUp(): void
    {
        $this->tracker = new Tracker();

        $registry = new MessageMappingRegistry([
            EventMock::messageName() => EventMock::class
        ]);
        $aggregateMessageJsonApiSerializable = new AggregateMessageJsonApiSerializable();
        $aggregateMessageStreamDeserializer = new AggregateMessageStreamDeserializer($registry);

        $this->serializer = new AggregateMessageSerializer(
            $this->tracker,
            $aggregateMessageJsonApiSerializable,
            $aggregateMessageStreamDeserializer
        );

        parent::setUp();
    }

    /**
     * @test
     */
    public function given_valid_correlation_id_in_encoded_envelope_then_is_used()
    {
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $correlationId = Uuid::v4();
        $encodedEnvelope = $this->buildEncodedEnvelope($correlationId->value(), $messageId, $aggregateId);

        /** @var EventMock $message */
        $message = $this->serializer->decode($encodedEnvelope)->getMessage();

        $this->assertEquals($messageId, $message->messageId());
        $this->assertEquals($correlationId, $this->tracker->correlationId($messageId));
        $this->assertNull($this->tracker->replyTo($messageId));
    }

    /**
     * @test
     */
    public function given_no_correlation_id_key_in_encoded_envelope_then_new_one_is_generated()
    {
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $encodedEnvelope = $this->buildEncodedEnvelope(false, $messageId, $aggregateId);

        /** @var EventMock $message */
        $message = $this->serializer->decode($encodedEnvelope)->getMessage();

        $this->assertEquals($messageId, $message->messageId());
        $this->assertTrue(RamseyUuid::isValid($this->tracker->correlationId($messageId)));
        $this->assertNull($this->tracker->replyTo($messageId));
    }

    /**
     * @test
     */
    public function given_null_correlation_id_in_encoded_envelope_then_new_one_is_generated()
    {
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $encodedEnvelope = $this->buildEncodedEnvelope(null, $messageId, $aggregateId);

        /** @var EventMock $message */
        $message = $this->serializer->decode($encodedEnvelope)->getMessage();

        $this->assertEquals($messageId, $message->messageId());
        $this->assertTrue(RamseyUuid::isValid($this->tracker->correlationId($messageId)));
        $this->assertNull($this->tracker->replyTo($messageId));
    }

    /**
     * @test
     */
    public function given_invalid_correlation_id_in_encoded_envelope_then_new_one_is_generated()
    {
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $encodedEnvelope = $this->buildEncodedEnvelope("FAKE_UUID", $messageId, $aggregateId);

        /** @var EventMock $message */
        $message = $this->serializer->decode($encodedEnvelope)->getMessage();

        $this->assertEquals($messageId, $message->messageId());
        $this->assertTrue(RamseyUuid::isValid($this->tracker->correlationId($messageId)));
        $this->assertNull($this->tracker->replyTo($messageId));
    }

    private function buildEncodedEnvelope($correlationId, Uuid $messageId, Uuid $aggregateId): array
    {
        $encodedEnvelope = [
            'headers' => [],
            'body' => \json_encode([
                'message_id' => $messageId->value(),
                'aggregate_id' => $aggregateId->value(),
                'name' => EventMock::messageName(),
                'payload' => [],
                'occurred_on' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]),
        ];

        if ($correlationId !== false) {
            $encodedEnvelope['headers']['x-correlation-id'] = $correlationId;
        }

        return $encodedEnvelope;
    }
}