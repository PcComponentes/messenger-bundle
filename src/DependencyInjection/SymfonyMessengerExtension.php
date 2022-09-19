<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\DependencyInjection;

use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageJsonApiSerializable;
use PcComponentes\Ddd\Util\Message\Serialization\JsonApi\AggregateMessageStreamDeserializer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class SymfonyMessengerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $occurredOnFormat = $config['aggregate_message']['occurred_on'];

        $container->addDefinitions([
            'pccom.messenger_bundle.aggregate_message.serializer.json_api_serializer' => new Definition(
                AggregateMessageJsonApiSerializable::class,
                [
                    $occurredOnFormat,
                ],
            ),
            'pccom.messenger_bundle.aggregate_message.serializer.stream_deserializer' => new Definition(
                AggregateMessageStreamDeserializer::class,
                [
                    new Reference('pccom.messenger_bundle.mapping_registry.aggregate_message'),
                    $occurredOnFormat,
                ],
            ),
        ]);
    }
}
