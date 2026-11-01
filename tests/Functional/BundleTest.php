<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Tests\Functional;

use MattLeads\MessengerCompressBundle\Command\GenerateKeyCommand;
use MattLeads\MessengerCompressBundle\MattLeadsMessengerCompressBundle;
use MattLeads\MessengerCompressBundle\Serializer\PayloadTransformingSerializer;
use MattLeads\MessengerCompressBundle\Stamp\CompressStamp;
use MattLeads\MessengerCompressBundle\Stamp\EncryptStamp;
use MattLeads\MessengerCompressBundle\Stamp\TransformedStamp;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class BundleTest extends TestCase
{
    public function testServicesAreWiredAndMessagesRoundTripThroughATransport(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        // Recent Messenger versions may wrap transport serializers (e.g. SigningSerializer), so assert on behaviour, not class.
        /** @var SerializerInterface $serializer */
        $serializer = $container->get(MattLeadsMessengerCompressBundle::SERIALIZER_ID);
        $encoded = $serializer->encode(new Envelope(new FunctionalMessage('x'), [new CompressStamp()]));
        self::assertSame('deflate', $encoded['headers'][PayloadTransformingSerializer::HEADER_COMPRESSION] ?? null);

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new FunctionalMessage(str_repeat('invoice-line;', 1000)), [new CompressStamp(), new EncryptStamp()]);

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        $received = iterator_to_array($transport->get(), false);
        self::assertCount(1, $received);

        /** @var Envelope $envelope */
        $envelope = $received[0];
        self::assertInstanceOf(FunctionalMessage::class, $envelope->getMessage());
        $stamp = $envelope->last(TransformedStamp::class);
        self::assertInstanceOf(TransformedStamp::class, $stamp);
        self::assertSame('deflate', $stamp->compression);
        self::assertSame('xchacha20poly1305', $stamp->encryption);
        self::assertSame('v1', $stamp->keyId);

        $kernel->shutdown();
    }

    public function testDefaultSerializerCanBeDecorated(): void
    {
        $kernel = new TestKernel(['decorate_default_serializer' => true, 'compression' => ['always' => true]]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        /** @var SerializerInterface $default */
        $default = $container->get('messenger.default_serializer');

        $encoded = $default->encode(new Envelope(new FunctionalMessage(str_repeat('x', 5000))));
        self::assertSame('deflate', $encoded['headers'][PayloadTransformingSerializer::HEADER_COMPRESSION] ?? null);

        $kernel->shutdown();
    }

    public function testGenerateKeyCommand(): void
    {
        $tester = new CommandTester(new GenerateKeyCommand());
        $tester->execute(['--id' => 'v2'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $tester->assertCommandIsSuccessful();
        $lines = explode("\n", trim($tester->getDisplay()));
        self::assertSame(32, \strlen((string) base64_decode($lines[0], true)));
        self::assertStringContainsString('MESSENGER_ENCRYPTION_KEY_V2', $tester->getDisplay());
    }
}
