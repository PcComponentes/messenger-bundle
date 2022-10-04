<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\Serializer;

use Assert\Assert;
use PcComponentes\Ddd\Util\Message\Serialization\Exception\MessageClassNotFoundException;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageJsonApiSerializable;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStream;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStreamDeserializer;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Throwable;

final class SimpleMessageSerializer extends DomainSerializer
{
    public function __construct(
        Tracker $tracker,
        private SimpleMessageJsonApiSerializable $serializer,
        private SimpleMessageStreamDeserializer $deserializer,
    ) {
        parent::__construct($tracker);
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $message = $this->streamFromEncodedEnvelope($encodedEnvelope);

        try {
            $simpleMessage = $this->deserializer->unserialize($message);
        } catch (MessageClassNotFoundException $exception) {
            throw new MessageDecodingFailedException('Message class not found', 0, $exception);
        } catch (Throwable $exception) {
            throw new MessageDecodingFailedException(
                'Unable to instantiate class for message. ' . $exception->getMessage(),
                0,
                $exception,
            );
        }

        $this->obtainDomainTrace($simpleMessage, $encodedEnvelope);

        $retryCount = \max(
            $this->extractHeaderRetryCount($encodedEnvelope),
            $this->buildRetryCountFromDeathHeader($encodedEnvelope),
        );

        return (new Envelope($simpleMessage))->with(new RedeliveryStamp($retryCount));
    }

    public function encode(Envelope $envelope): array
    {
        return [
            'body' => $this->serializer->serialize(
                $envelope->getMessage(),
            ),
            'headers' => [
                'Content-Type' => 'application/json',
                'x-correlation-id' => $this->tracker()->correlationId(),
                'x-reply-to' => $this->tracker()->replyTo(),
                'x-retry-count' => $this->extractEnvelopeRetryCount($envelope),
            ],
        ];
    }

    private function streamFromEncodedEnvelope(array $encodedEnvelope): SimpleMessageStream
    {
        $body = \json_decode($encodedEnvelope['body'], true);
        $this->assertContent($body);
        $event = $body['data'];

        if (null === $event) {
            throw new \InvalidArgumentException('The body of message is null');
        }

        return new SimpleMessageStream(
            $event['message_id'],
            $event['type'],
            \json_encode($event['attributes']) ?: '',
        );
    }

    private function assertContent(?array $content)
    {
        Assert::lazy()->tryAll()
            ->that($content['data'], 'data')->isArray()
            ->keyExists('message_id')
            ->keyExists('type')
            ->keyExists('attributes')
            ->verifyNow()
        ;

        Assert::lazy()->tryAll()
            ->that($content['data']['message_id'], 'message_id')->uuid()
            ->that($content['data']['type'], 'type')->string()->notEmpty()
            ->that($content['data']['attributes'], 'attributes')->isArray()
            ->verifyNow()
        ;
    }
}
