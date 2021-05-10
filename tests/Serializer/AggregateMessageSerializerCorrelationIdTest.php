<?php declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Serializer;

use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageJsonApiSerializable;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageStreamDeserializer;
use PcComponentes\Ddd\Util\Message\Serialization\MessageMappingRegistry;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use PcComponentes\SymfonyMessengerBundle\Serializer\AggregateMessageSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

class AggregateMessageSerializerCorrelationIdTest extends TestCase
{
    private CONST MESSAGE_ID = 'd496c2ef-f9a5-4702-9aaa-53fa8ad10d3b';

    private AggregateMessageJsonApiSerializable $serializer;
    private AggregateMessageStreamDeserializer $deserializer;
    private Tracker $tracker;
    private MessageMappingRegistry $registry;
    private AggregateMessageSerializer $aggregateMessageSerializer;

    protected function setUp(): void
    {
        $this->registry = new MessageMappingRegistry([
            AggregateCreated::messageName() => AggregateCreated::class
        ]);

        $this->serializer = new AggregateMessageJsonApiSerializable();
        $this->deserializer = new AggregateMessageStreamDeserializer($this->registry);
        $this->tracker = new Tracker();

        $this->aggregateMessageSerializer = new AggregateMessageSerializer(
            $this->tracker,
            $this->serializer,
            $this->deserializer
        );
    }

    /**
     * @test
     */
    public function given_null_correlation_id_header_when_message_then_throw_exception()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Message has a null value for x-correlation-id header.');
        $message = $this->getMessage(null);

        $this->aggregateMessageSerializer->decode($message);
    }

    /**
     * @test
     */
    public function given_valid_correlation_id_header_when_message_then_ok()
    {
        $correlationId = 'c1581a46-f645-4332-9165-335d1b666a46';
        $message = $this->getMessage($correlationId);
        $envelope = $this->aggregateMessageSerializer->decode($message);

        $this->assertEquals($correlationId, $this->tracker->correlationId(Uuid::from(self::MESSAGE_ID)));
        $this->assertInstanceOf(Envelope::class, $envelope);
    }

    /**
     * @test
     */
    public function given_no_correlation_id_header_when_message_then_ok()
    {
        $message = $this->getMessage(false);
        $envelope = $this->aggregateMessageSerializer->decode($message);

        $this->assertNotNull($this->tracker->correlationId(Uuid::from(self::MESSAGE_ID)));
        $this->assertInstanceOf(Envelope::class, $envelope);
    }

    private function getMessage($correlationId): array
    {
        $message = [
            'body' => \json_encode([
                'message_id' => self::MESSAGE_ID,
                'name' => AggregateCreated::messageName(),
                'version' => AggregateCreated::messageVersion(),
                'type' => 'domain_event',
                'payload' => [
                    'id' => '9e7a9d6a-f9cb-4de8-950b-a84c2c1abe66',
                    'familyId' => '6ce04f54-4c48-4d76-b37a-12648d38536d',
                ],
                'aggregate_id' => '9e7a9d6a-f9cb-4de8-950b-a84c2c1abe66',
                'occurred_on' => '2021-05-07T08:44:48+00:00',
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        if ($correlationId !== false) {
            $message['headers']['x-correlation-id'] = $correlationId;
        }

        return $message;
    }
}