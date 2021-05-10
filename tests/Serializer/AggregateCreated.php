<?php declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Serializer;

use PcComponentes\Ddd\Domain\Model\DomainEvent;
use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;

final class AggregateCreated extends DomainEvent
{
    private Uuid $id;
    private Uuid $familyId;

    public static function messageName(): string
    {
        return 'pccomponentes.context.1.domain_event.aggregate.created';
    }

    public static function messageVersion(): string
    {
        return '1';
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function familyId(): Uuid
    {
        return $this->familyId;
    }

    protected function assertPayload(): void
    {
        $payload = $this->messagePayload();

        $this->id = Uuid::from($payload['id']);
        $this->familyId = Uuid::from($payload['familyId']);
    }
}
