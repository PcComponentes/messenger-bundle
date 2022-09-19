<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfony_messenger');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('aggregate_message')
                    ->children()
                        ->arrayNode('occurred_on')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('format')
                                ->values(['U', 'U.v', 'U.u'])
                                ->defaultValue('U')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
