<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\DependencyInjection;

use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageJsonApiSerializable;
use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageStreamDeserializer;
use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageJsonApiSerializable;
use Pccomponentes\Ddd\Util\Message\Serialization\JsonApi\SimpleMessageStreamDeserializer;
use PcComponentes\DddLogging\DomainTrace\Tracker;
use PcComponentes\SymfonyMessengerBundle\Serializer\AggregateMessageSerializer;
use PcComponentes\SymfonyMessengerBundle\Serializer\SimpleMessageSerializer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SerializerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->register(Tracker::class, Tracker::class);

        $container->addDefinitions([
            'mybundle.aggregate_message.serializer' => new Definition(
                AggregateMessageSerializer::class,
                [
                    new Reference(Tracker::class),
                    new Reference('mybundle.aggregate_message.serializer.json_api_serializer'),
                    new Reference('mybundle.aggregate_message.serializer.stream_deserializer'),
                ],
            ),
            'mybundle.aggregate_message.serializer.json_api_serializer' => new Definition(
                AggregateMessageJsonApiSerializable::class,
            ),
            'mybundle.aggregate_message.serializer.stream_deserializer' => new Definition(
                AggregateMessageStreamDeserializer::class,
                [
                    new Reference('mybundle.mapping_registry.aggregate_message'),
                ],
            ),
        ]);

        $container->addDefinitions([
            'mybundle.simple_message.serializer' => new Definition(
                SimpleMessageSerializer::class,
                [
                    new Reference(Tracker::class),
                    new Reference('mybundle.simple_message.serializer.json_api_serializer'),
                    new Reference('mybundle.simple_message.serializer.stream_deserializer'),
                ],
            ),
            'mybundle.simple_message.serializer.json_api_serializer' => new Definition(
                SimpleMessageJsonApiSerializable::class,
            ),
            'mybundle.simple_message.serializer.stream_deserializer' => new Definition(
                SimpleMessageStreamDeserializer::class,
                [
                    new Reference('mybundle.mapping_registry.simple_message'),
                ],
            ),
        ]);
    }
}
