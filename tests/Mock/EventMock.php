<?php declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Mock;

use PcComponentes\Ddd\Domain\Model\DomainEvent;
use PcComponentes\Ddd\Domain\Model\ValueObject\DateTimeValueObject;
use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;

final class EventMock extends DomainEvent
{
    private const NAME = 'event_mock';
    private const VERSION = '1';

    public static function from(Uuid $aggregateId): self {
        return self::fromPayload(
            Uuid::v4(),
            $aggregateId,
            new DateTimeValueObject(),
            [],
            0,
        );
    }

    public static function messageName(): string
    {
        return 'pccomponentes.'
            .'service.'
            .self::VERSION.'.'
            .self::messageType().'.'
            .'aggregate.'
            .self::NAME;
    }

    public static function messageVersion(): string
    {
        return self::VERSION;
    }

    protected function assertPayload(): void
    {

    }
}
