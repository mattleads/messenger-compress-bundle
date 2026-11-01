<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * @internal
 */
final class Configuration
{
    public static function build(NodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('Root node must be an array node.');
        }

        $root
            ->children()
                ->scalarNode('inner_serializer')
                    ->info('Service id of the serializer to wrap. Defaults to the Messenger default serializer.')
                    ->defaultValue('messenger.default_serializer')
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('decorate_default_serializer')
                    ->info('When true, the default Messenger serializer is decorated so every transport is affected. When false (default), reference "mattleads_messenger_compress.serializer" from the transports you choose.')
                    ->defaultFalse()
                ->end()
                ->enumNode('encoding')
                    ->info('How transformed (binary) bodies are written on the wire. "base64" is safe for every transport; "raw" saves ~33% on transports that accept binary bodies (AMQP).')
                    ->values(['base64', 'raw'])
                    ->defaultValue('base64')
                ->end()
                ->arrayNode('compression')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default_algorithm')
                            ->info('Compressor used when a CompressStamp does not name one: deflate, gzip or zstd (needs ext-zstd).')
                            ->defaultValue('deflate')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('level')
                            ->info('Compression level passed to the compressor (algorithm-specific). Null = compressor default.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('auto_threshold')
                            ->info('Compress any payload whose serialized body is at least this many bytes, even without a stamp. Null disables.')
                            ->defaultNull()
                            ->min(1)
                        ->end()
                        ->booleanNode('always')
                            ->info('Compress every payload regardless of size or stamps (opt out per message with PlainStamp).')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('encryption')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->booleanNode('always')
                            ->info('Encrypt every payload (opt out per message with PlainStamp).')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('active_key')
                            ->info('Id of the key used for new messages. Defaults to the last configured key. Older keys stay available for decoding (rotation).')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('keys')
                            ->info('Map of key id => base64-encoded 32-byte key. Generate with "bin/console messenger-compress:generate-key".')
                            ->useAttributeAsKey('id')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
