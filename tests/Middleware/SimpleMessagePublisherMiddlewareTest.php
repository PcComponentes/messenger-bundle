<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Middleware;

use PcComponentes\Ddd\Domain\Model\ValueObject\DateTimeValueObject;
use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;
use PcComponentes\SymfonyMessengerBundle\Bus\AllHandledStampExtractor;
use PcComponentes\SymfonyMessengerBundle\Middleware\SimpleMessagePublisherMiddleware;
use PcComponentes\SymfonyMessengerBundle\Tests\Mock\CommandMock;
use PcComponentes\SymfonyMessengerBundle\Tests\Mock\EventMock;
use PcComponentes\SymfonyMessengerBundle\Tests\Mock\FakeBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

final class SimpleMessagePublisherMiddlewareTest extends MiddlewareTestCase
{
    private MessageBusInterface $bus;
    private AllHandledStampExtractor $extractor;

    public function setUp(): void
    {
        $this->bus = new FakeBus();
        $this->extractor = new AllHandledStampExtractor();

        parent::setUp();
    }

    public function test_given_converters_dispatch_only_one_command_per_converter_then_ok()
    {
        $firstMessageId = Uuid::v4();
        $secondMessageId = Uuid::v4();

        $envelope = new Envelope(EventMock::fromPayload(Uuid::v4(), Uuid::v4(), DateTimeValueObject::now(), []));
        $envelope = $envelope->with(
            new HandledStamp(CommandMock::fromPayload($firstMessageId, []), 'ConverterA'),
            new HandledStamp(CommandMock::fromPayload($secondMessageId, []), 'ConverterB'),
        );

        $middleware = new SimpleMessagePublisherMiddleware($this->bus, $this->extractor);
        $middleware->handle($envelope, $this->getStackMock());

        $this->assertCount(2, $this->bus->getMessages());
        $this->assertEquals($firstMessageId, $this->bus->getMessages()[0]->getMessage()->messageId()->value());
        $this->assertEquals($secondMessageId, $this->bus->getMessages()[1]->getMessage()->messageId()->value());
    }

    public function test_given_converters_dispatch_one_command_and_the_other_does_not_dispatch_anything_then_ok()
    {
        $firstMessageId = Uuid::v4();

        $envelope = new Envelope(EventMock::fromPayload(Uuid::v4(), Uuid::v4(), DateTimeValueObject::now(), []));
        $envelope = $envelope->with(
            new HandledStamp(CommandMock::fromPayload($firstMessageId, []), 'ConverterA'),
            new HandledStamp(null, 'ConverterB'),
        );

        $middleware = new SimpleMessagePublisherMiddleware($this->bus, $this->extractor);
        $middleware->handle($envelope, $this->getStackMock());

        $this->assertCount(1, $this->bus->getMessages());
        $this->assertEquals($firstMessageId, $this->bus->getMessages()[0]->getMessage()->messageId()->value());
    }

    public function test_given_converters_dispatch_both_one_command_and_an_array_of_commands_then_ok()
    {
        $firstMessageId = Uuid::v4();
        $secondMessageId = Uuid::v4();
        $thirdMessageId = Uuid::v4();

        $envelope = new Envelope(EventMock::fromPayload(Uuid::v4(), Uuid::v4(), DateTimeValueObject::now(), []));
        $envelope = $envelope->with(
            new HandledStamp(CommandMock::fromPayload($firstMessageId, []), 'ConverterA'),
            new HandledStamp([
                    CommandMock::fromPayload($secondMessageId, []),
                    CommandMock::fromPayload($thirdMessageId, []),
                ],
                'ConverterB'
            ),
        );

        $middleware = new SimpleMessagePublisherMiddleware($this->bus, $this->extractor);
        $middleware->handle($envelope, $this->getStackMock());

        $this->assertCount(3, $this->bus->getMessages());
        $this->assertEquals($firstMessageId, $this->bus->getMessages()[0]->getMessage()->messageId()->value());
        $this->assertEquals($secondMessageId, $this->bus->getMessages()[1]->getMessage()->messageId()->value());
        $this->assertEquals($thirdMessageId, $this->bus->getMessages()[2]->getMessage()->messageId()->value());
    }

    public function test_given_converters_dispatch_both_one_command_and_an_array_of_commands_with_one_null_then_ok()
    {
        $firstMessageId = Uuid::v4();
        $secondMessageId = Uuid::v4();
        $thirdMessageId = Uuid::v4();

        $envelope = new Envelope(EventMock::fromPayload(Uuid::v4(), Uuid::v4(), DateTimeValueObject::now(), []));
        $envelope = $envelope->with(
            new HandledStamp(CommandMock::fromPayload($firstMessageId, []), 'ConverterA'),
            new HandledStamp([
                CommandMock::fromPayload($secondMessageId, []),
                CommandMock::fromPayload($thirdMessageId, []),
                null,
            ],
                'ConverterB'
            ),
        );

        $middleware = new SimpleMessagePublisherMiddleware($this->bus, $this->extractor);
        $middleware->handle($envelope, $this->getStackMock());

        $this->assertCount(3, $this->bus->getMessages());
        $this->assertEquals($firstMessageId, $this->bus->getMessages()[0]->getMessage()->messageId()->value());
        $this->assertEquals($secondMessageId, $this->bus->getMessages()[1]->getMessage()->messageId()->value());
        $this->assertEquals($thirdMessageId, $this->bus->getMessages()[2]->getMessage()->messageId()->value());
    }
}
