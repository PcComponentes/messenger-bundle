<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Serializer;

use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageJsonApiSerializable;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStreamDeserializer;
use PcComponentes\Ddd\Util\Message\Serialization\MessageMappingRegistry;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use PcComponentes\SymfonyMessengerBundle\Serializer\SimpleMessageSerializer;
use PcComponentes\SymfonyMessengerBundle\Tests\Mock\CommandMock;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class SimpleMessageSerialiazerTest extends TestCase
{
    private Tracker $tracker;
    private SimpleMessageSerializer $serializer;

    public function setUp(): void
    {
        $this->tracker = new Tracker();

        $registry = new MessageMappingRegistry([
            CommandMock::messageName() => CommandMock::class
        ]);
        $simpleMessageJsonApiSerializable = new SimpleMessageJsonApiSerializable();
        $simpleMessageStreamDeserializer = new SimpleMessageStreamDeserializer($registry);

        $this->serializer = new SimpleMessageSerializer(
            $this->tracker,
            $simpleMessageJsonApiSerializable,
            $simpleMessageStreamDeserializer
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
        $encodedEnvelope = $this->buildEncodedEnvelopeWithCorrelationId($correlationId->value(), $messageId, $aggregateId);

        /** @var CommandMock $message */
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
        $encodedEnvelope = $this->buildEncodedEnvelope($messageId, $aggregateId);

        /** @var CommandMock $message */
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
        $encodedEnvelope = $this->buildEncodedEnvelopeWithCorrelationId(null, $messageId, $aggregateId);

        /** @var CommandMock $message */
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
        $encodedEnvelope = $this->buildEncodedEnvelopeWithCorrelationId("FAKE_UUID", $messageId, $aggregateId);

        /** @var CommandMock $message */
        $message = $this->serializer->decode($encodedEnvelope)->getMessage();

        $this->assertEquals($messageId, $message->messageId());
        $this->assertTrue(RamseyUuid::isValid($this->tracker->correlationId($messageId)));
        $this->assertNull($this->tracker->replyTo($messageId));
    }

    /**
     * @test
     */
    public function given_pristine_message_then_reply_count_is_zero()
    {
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $headers = [
            'x-retry-count' => 0
        ];

        $encodedEnvelope = $this->buildEncodedEnvelope($messageId, $aggregateId, $headers);
        $envelope = $this->serializer->decode($encodedEnvelope);
        $retryCount = $envelope->last(RedeliveryStamp::class)?->getRetryCount();

        $this->assertEquals(0, $retryCount);
    }

    /**
     * @test
     */
    public function given_retried_message_using_messenger_retry_logic_then_reply_count_is_expected_one()
    {
        $expectedRetries = 3;
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $headers = [
            'x-retry-count' => $expectedRetries
        ];

        $encodedEnvelope = $this->buildEncodedEnvelope($messageId, $aggregateId, $headers);
        $envelope = $this->serializer->decode($encodedEnvelope);
        $retryCount = $envelope->last(RedeliveryStamp::class)?->getRetryCount();

        $this->assertEquals($expectedRetries, $retryCount);
    }

    /**
     * @test
     */
    public function given_retried_message_using_rabbitmq_dead_letter_retry_logic_then_reply_count_is_the_expected_one()
    {
        $letterRetries = 2;
        $expectedRetries = 2 * $letterRetries;
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $headers = [
            'x-retry-count' => 0,
            'x-death' => [
                [
                    'count' => $letterRetries,
                    'exchange' => 'dead_letter',
                    'queue' => 'commands.dead_letter_2',
                    'reason' => 'expired',
                    'routing-keys' => [CommandMock::messageName()],
                    'time' => new \AMQPTimestamp(floatval('1647193731'))
                ],
                [
                    'count' => $letterRetries,
                    'exchange' => 'dead_letter',
                    'queue' => 'commands.dead_letter_1',
                    'reason' => 'rejected',
                    'routing-keys' => [CommandMock::messageName()],
                    'time' => new \AMQPTimestamp(floatval('1647193701'))
                ]
            ],
            'x-first-death-exchange' => 'commands',
            'x-first-death-queue' => 'commands',
            'x-first-death-reason' => 'rejected',
        ];

        $encodedEnvelope = $this->buildEncodedEnvelope($messageId, $aggregateId, $headers);
        $envelope = $this->serializer->decode($encodedEnvelope);
        $retryCount = $envelope->last(RedeliveryStamp::class)?->getRetryCount();

        $this->assertEquals($expectedRetries, $retryCount);
    }

    /**
     * @test
     */
    public function given_retried_message_using_rabbitmq_dead_letter_retry_logic_and_messenger_one_then_reply_count_is_the_highest_one()
    {
        $expectedRetries = 5;
        $highestRetries = 2 * $expectedRetries;
        $messageId = Uuid::v4();
        $aggregateId = Uuid::v4();
        $headers = [
            'x-retry-count' => $expectedRetries,
            'x-death' => [
                [
                    'count' => $expectedRetries,
                    'exchange' => 'dead_letter',
                    'queue' => 'commands.dead_letter_2',
                    'reason' => 'expired',
                    'routing-keys' => [CommandMock::messageName()],
                    'time' => new \AMQPTimestamp(floatval('1647193731'))
                ],
                [
                    'count' => $expectedRetries,
                    'exchange' => 'dead_letter',
                    'queue' => 'commands.dead_letter_1',
                    'reason' => 'rejected',
                    'routing-keys' => [CommandMock::messageName()],
                    'time' => new \AMQPTimestamp(floatval('1647193701'))
                ]
            ],
            'x-first-death-exchange' => 'commands',
            'x-first-death-queue' => 'commands',
            'x-first-death-reason' => 'rejected',
        ];

        $encodedEnvelope = $this->buildEncodedEnvelope($messageId, $aggregateId, $headers);
        $envelope = $this->serializer->decode($encodedEnvelope);
        $retryCount = $envelope->last(RedeliveryStamp::class)?->getRetryCount();

        $this->assertEquals($highestRetries, $retryCount);
    }

    private function buildEncodedEnvelope(Uuid $messageId, Uuid $aggregateId, array $headers = null): array
    {
        $encodedEnvelope = [
            'headers' => (null !== $headers) ? $headers : [],
            'body' => \json_encode([
                "data" => [
                    'message_id' => $messageId->value(),
                    'type' => CommandMock::messageName(),
                    'attributes' => [],
                ]
            ]),
        ];

        return $encodedEnvelope;
    }

    private function buildEncodedEnvelopeWithCorrelationId($correlationId, Uuid $messageId, Uuid $aggregateId)
    {
        $headers['x-correlation-id'] = $correlationId;

        return $this->buildEncodedEnvelope($messageId, $aggregateId, $headers);
    }
}
