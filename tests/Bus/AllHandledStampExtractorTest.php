<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Bus;

use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageJsonApiSerializable;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStreamDeserializer;
use PcComponentes\Ddd\Util\Message\Serialization\MessageMappingRegistry;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use PcComponentes\SymfonyMessengerBundle\Bus\AllHandledStampExtractor;
use PcComponentes\SymfonyMessengerBundle\Middleware\SimpleMessagePublisherMiddleware;
use PcComponentes\SymfonyMessengerBundle\Serializer\SimpleMessageSerializer;
use PcComponentes\SymfonyMessengerBundle\Tests\Mock\CommandMock;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class AllHandledStampExtractorTest extends TestCase
{
    private AllHandledStampExtractor $extractor;

    public function setUp(): void
    {
        $this->extractor = new AllHandledStampExtractor();

        parent::setUp();
    }

    public function test_given_single_command_is_returned_then_on()
    {
        $envelope = new Envelope(new \stdClass());
        $handledStamp = new HandledStamp(CommandMock::fromPayload(Uuid::v4(), []), 'handler');
        $handledEnvelope = $envelope->with($handledStamp);

        $results = $this->extractor->extract($handledEnvelope);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(CommandMock::class, $results[0]);
    }

    public function test_given_single_command_and_null_are_returned_then_on()
    {
        $envelope = new Envelope(new \stdClass());
        $handledEnvelope = $envelope->with(
            new HandledStamp(CommandMock::fromPayload(Uuid::v4(), []), 'converterA'),
            new HandledStamp(null, 'converterB')
        );

        $results = $this->extractor->extract($handledEnvelope);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(CommandMock::class, $results[0]);
        $this->assertNull($results[1]);
    }

    public function test_given_single_and_multiple_command_are_returned_then_ok()
    {
        $envelope = new Envelope(new \stdClass());
        $firstCommandId = Uuid::v4();
        $secondCommandId = Uuid::v4();
        $thirdCommandId = Uuid::v4();

        $firstConverterHandledStamp = new HandledStamp(
            CommandMock::fromPayload($thirdCommandId, []),
            'firstHandler'
        );
        $handledEnvelope = $envelope->with($firstConverterHandledStamp);

        $secondConverterHandledStamp = new HandledStamp([
            CommandMock::fromPayload($firstCommandId, []),
            CommandMock::fromPayload($secondCommandId, []),
        ], 'secondHandler');
        $handledEnvelope = $handledEnvelope->with($secondConverterHandledStamp);

        $results = $this->extractor->extract($handledEnvelope);

        $this->assertCount(2, $results);

        $this->assertInstanceOf(CommandMock::class, $results[0]);
        $this->assertEquals($thirdCommandId, $results[0]->messageId());

        $this->assertIsArray($results[1]);
        $this->assertInstanceOf(CommandMock::class, $results[1][0]);
        $this->assertEquals($firstCommandId, $results[1][0]->messageId());
        $this->assertInstanceOf(CommandMock::class, $results[1][1]);
        $this->assertEquals($secondCommandId, $results[1][1]->messageId());
    }
}
