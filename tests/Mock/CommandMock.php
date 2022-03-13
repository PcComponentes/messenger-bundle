<?php declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Mock;

use PcComponentes\Ddd\Application\Command;

final class CommandMock extends Command
{
    private const NAME = 'command_mock';
    private const VERSION = '1';

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
