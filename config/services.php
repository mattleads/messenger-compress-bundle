<?php

declare(strict_types=1);

use MattLeads\MessengerCompressBundle\Command\GenerateKeyCommand;
use MattLeads\MessengerCompressBundle\Compression\CompressorRegistry;
use MattLeads\MessengerCompressBundle\Compression\DeflateCompressor;
use MattLeads\MessengerCompressBundle\Compression\GzipCompressor;
use MattLeads\MessengerCompressBundle\Compression\ZstdCompressor;
use MattLeads\MessengerCompressBundle\Encryption\EncryptorInterface;
use MattLeads\MessengerCompressBundle\Encryption\KeyRing;
use MattLeads\MessengerCompressBundle\Encryption\SodiumEncryptor;
use MattLeads\MessengerCompressBundle\MattLeadsMessengerCompressBundle;
use MattLeads\MessengerCompressBundle\Serializer\CompressionPolicy;
use MattLeads\MessengerCompressBundle\Serializer\EncryptionPolicy;
use MattLeads\MessengerCompressBundle\Serializer\PayloadTransformingSerializer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(DeflateCompressor::class)->tag(MattLeadsMessengerCompressBundle::COMPRESSOR_TAG);
    $services->set(GzipCompressor::class)->tag(MattLeadsMessengerCompressBundle::COMPRESSOR_TAG);
    if (ZstdCompressor::isAvailable()) {
        $services->set(ZstdCompressor::class)->tag(MattLeadsMessengerCompressBundle::COMPRESSOR_TAG);
    }

    $services->set(CompressorRegistry::class)
        ->args([tagged_iterator(MattLeadsMessengerCompressBundle::COMPRESSOR_TAG)]);

    $services->set(SodiumEncryptor::class);
    $services->alias(EncryptorInterface::class, SodiumEncryptor::class);

    $services->set(KeyRing::class);

    $services->set(CompressionPolicy::class);
    $services->set(EncryptionPolicy::class);

    $services->set(PayloadTransformingSerializer::class)
        ->args([
            '$inner' => service('messenger.default_serializer'),
            '$compressors' => service(CompressorRegistry::class),
            '$encryptor' => service(EncryptorInterface::class),
            '$keyRing' => null,
            '$compression' => service(CompressionPolicy::class),
            '$encryption' => service(EncryptionPolicy::class),
            '$encoding' => 'base64',
        ]);
    $services->alias(MattLeadsMessengerCompressBundle::SERIALIZER_ID, PayloadTransformingSerializer::class)->public();

    $services->set(GenerateKeyCommand::class)->tag('console.command');
};
