<?php
declare(strict_types=1);

namespace PcComponentes\SymfonyMessengerBundle\DependencyInjection;

use Pccomponentes\Ddd\Application\Command;
use Pccomponentes\Ddd\Application\Query;
use Pccomponentes\Ddd\Domain\Model\DomainEvent;
use Pccomponentes\Ddd\Domain\Model\Snapshot;
use Pccomponentes\Ddd\Util\Message\AggregateMessage;
use Pccomponentes\Ddd\Util\Message\Serialization\MessageMappingRegistry;
use Pccomponentes\Ddd\Util\Message\SimpleMessage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class MappingRegistryCompilerPass implements CompilerPassInterface
{
    private const PREFIX_SERVICE_ALIAS = 'pccom.messenger_bundle.mapping_registry';
    private const INTERFACES = [
        'simple_message' => SimpleMessage::class,
        'command' => Command::class,
        'query' => Query::class,
        'aggregate_message' => AggregateMessage::class,
        'domain_event' => DomainEvent::class,
        'snapshot' => Snapshot::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        $rootDir = \sprintf('%s/', $container->getParameter('kernel.root_dir'));
        $files = $this->listDirectory($rootDir);

        foreach (self::INTERFACES as $name => $interface) {
            $alias = $this->aliasFromTypeMessage($name);
            $definition = $this->messageMappingRegistryDefinition($interface, $files);
            
            $container->addDefinitions([
                $alias => $definition,
            ]);
        }
    }

    private function listDirectory(string $path): array
    {
        if (false === \is_dir($path)) {
            throw new \Exception('no es ruta valida');
        }

        $files = [];

        if ($dh = \opendir($path)) {
            while (false !== ($filename = \readdir($dh))) {
                $isDirectory = \is_dir($path . $filename);

                if (true === $isDirectory && false === \in_array($filename, ['.', '..'])) {
                    $files = \array_merge(
                        $files,
                        $this->listDirectory($path . $filename . '/'),
                    );

                    continue;
                }

                if (false !== \is_dir($path . $filename)) {
                    continue;
                }
    
                if (0 === preg_match("/^.*\.(php)$/", $filename)) {
                    continue;
                }
                
                $files[] = $path . $filename;
            }

            \closedir($dh);
        }

        return $files;
    }

    private function aliasFromTypeMessage(string $type): string
    {
        return \implode(
            '.',
            [
                self::PREFIX_SERVICE_ALIAS,
                $type,
            ],
        );
    }

    private function messageMappingRegistryDefinition(string $interface, array $files): Definition
    {
        $classes = $this->classFromFiles(
            $files,
            $interface,
        );

        return new Definition(
            MessageMappingRegistry::class,
            [
                $this->generateMapping($classes),
            ],
        );
    }

    private function classFromFiles(array $files, string $interface): array
    {
        foreach ($files as $file) {
            require_once $file;
        }

        $classes = [];

        foreach (\get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            if (false === $reflection->isSubclassOf($interface)) {
                continue;
            }

            if (false === $reflection->isFinal()) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    private function generateMapping(array $classes): array
    {
        $result = [];

        foreach ($classes as $class) {
            $result[$class::messageName()] = $class;
        }

        return $result;
    }
}
