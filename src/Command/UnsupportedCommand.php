<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Command;

use PcComponentes\Ddd\Application\Command;
use PcComponentes\Ddd\Domain\Model\ValueObject\Uuid;

final class UnsupportedCommand extends Command
{
    private static string $messageName = 'unsupported';
    private static string $messageVersion = '0';

    public static function create(string $messageId, string $messageName, array $payload): self
    {
        $fields = \explode('.', $messageName);

        self::$messageName = $messageName;
        self::$messageVersion = $fields[2] ?? '1';

        return self::fromPayload(
            Uuid::from($messageId),
            $payload,
        );
    }

    public static function messageName(): string
    {
        return self::$messageName;
    }

    public static function messageVersion(): string
    {
        return self::$messageVersion;
    }

    protected function assertPayload(): void
    {
    }
}
