<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Tests\Middleware;

use PcComponentes\SymfonyMessengerBundle\Middleware\HandlerExceptionCatcherMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class HandlerExceptionCatcherMiddlewareTest extends TestCase
{
    public function test_given_a_handler_failed_exception_with_named_wrapped_exceptions_then_it_throws_the_first_wrapped_exception(): void
    {
        $wrappedException = new \RuntimeException('expected');
        $middleware = new HandlerExceptionCatcherMiddleware();
        $envelope = new Envelope(new \stdClass());
        $nextMiddleware = new class($envelope, $wrappedException) implements MiddlewareInterface {
            public function __construct(
                private readonly Envelope $envelope,
                private readonly \RuntimeException $wrappedException,
            ) {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw new HandlerFailedException(
                    $this->envelope,
                    ['handler_name' => $this->wrappedException],
                );
            }
        };
        $stack = new class($nextMiddleware) implements StackInterface {
            public function __construct(private readonly MiddlewareInterface $middleware)
            {
            }

            public function next(): MiddlewareInterface
            {
                return $this->middleware;
            }
        };

        $this->expectExceptionObject($wrappedException);

        $middleware->handle($envelope, $stack);
    }
}
