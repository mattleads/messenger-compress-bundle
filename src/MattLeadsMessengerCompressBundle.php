<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle;

use MattLeads\MessengerCompressBundle\Compression\CompressorInterface;
use MattLeads\MessengerCompressBundle\DependencyInjection\Configuration;
use MattLeads\MessengerCompressBundle\Encryption\KeyRing;
use MattLeads\MessengerCompressBundle\Serializer\CompressionPolicy;
use MattLeads\MessengerCompressBundle\Serializer\EncryptionPolicy;
use MattLeads\MessengerCompressBundle\Serializer\PayloadTransformingSerializer;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class MattLeadsMessengerCompressBundle extends AbstractBundle
{
    public const COMPRESSOR_TAG = 'mattleads_messenger_compress.compressor';
    public const SERIALIZER_ID = 'mattleads_messenger_compress.serializer';

    protected string $extensionAlias = 'mattleads_messenger_compress';

    public function configure(DefinitionConfigurator $definition): void
    {
        Configuration::build($definition->rootNode());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->registerForAutoconfiguration(CompressorInterface::class)->addTag(self::COMPRESSOR_TAG);

        /** @var array{default_algorithm: string, level: ?int, auto_threshold: ?int, always: bool} $compression */
        $compression = $config['compression'];
        /** @var array{enabled: bool, always: bool, active_key: ?string, keys: array<string, string>} $encryption */
        $encryption = $config['encryption'];

        $builder->getDefinition(CompressionPolicy::class)
            ->setArguments([
                '$defaultAlgorithm' => $compression['default_algorithm'],
                '$level' => $compression['level'],
                '$autoThreshold' => $compression['auto_threshold'],
                '$always' => $compression['always'],
            ]);

        $builder->getDefinition(EncryptionPolicy::class)
            ->setArguments([
                '$enabled' => $encryption['enabled'],
                '$always' => $encryption['always'],
            ]);

        if ($encryption['enabled']) {
            if ([] === $encryption['keys']) {
                throw new \InvalidArgumentException('mattleads_messenger_compress.encryption.enabled is true but no keys are configured. Generate one with "bin/console messenger-compress:generate-key".');
            }
            $activeKey = $encryption['active_key'] ?? array_key_last($encryption['keys']);
            $builder->getDefinition(KeyRing::class)
                ->setFactory([KeyRing::class, 'fromEncodedKeys'])
                ->setArguments([$encryption['keys'], $activeKey]);
        } else {
            $builder->removeDefinition(KeyRing::class);
        }

        $serializer = $builder->getDefinition(PayloadTransformingSerializer::class)
            ->replaceArgument('$inner', new Reference($config['inner_serializer']))
            ->replaceArgument('$keyRing', $encryption['enabled'] ? new Reference(KeyRing::class) : null)
            ->replaceArgument('$encoding', $config['encoding']);

        if ($config['decorate_default_serializer']) {
            // Wrap the default serializer so every transport without an explicit serializer gets the behaviour.
            $serializer->setDecoratedService('messenger.default_serializer');
            $serializer->replaceArgument('$inner', new Reference(PayloadTransformingSerializer::class . '.inner'));
        }
    }
}
