<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Tests\Functional;

use MattLeads\MessengerCompressBundle\MattLeadsMessengerCompressBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const KEY_V1 = 'Zm9vYmFyYmF6cXV4Zm9vYmFyYmF6cXV4Zm9vYmFyYmE='; // 32 bytes, fixed for tests

    /** @param array<string, mixed> $bundleConfig */
    public function __construct(private readonly array $bundleConfig = [])
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new MattLeadsMessengerCompressBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'messenger' => [
                'transports' => [
                    // serialize=true makes the in-memory transport run the serializer, like a real broker would
                    'async' => ['dsn' => 'in-memory://?serialize=true', 'serializer' => MattLeadsMessengerCompressBundle::SERIALIZER_ID],
                ],
                'routing' => [FunctionalMessage::class => 'async'],
            ],
        ]);
        $container->extension('mattleads_messenger_compress', $this->bundleConfig + [
            'encryption' => ['enabled' => true, 'keys' => ['v1' => self::KEY_V1]],
        ]);
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/mattleads_messenger_compress/project';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/mattleads_messenger_compress/' . md5(serialize($this->bundleConfig)) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/mattleads_messenger_compress/log';
    }
}

final class FunctionalMessage
{
    public function __construct(public string $body) {}
}
