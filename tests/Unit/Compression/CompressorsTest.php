<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Tests\Unit\Compression;

use MattLeads\MessengerCompressBundle\Compression\CompressorInterface;
use MattLeads\MessengerCompressBundle\Compression\CompressorRegistry;
use MattLeads\MessengerCompressBundle\Compression\DeflateCompressor;
use MattLeads\MessengerCompressBundle\Compression\GzipCompressor;
use MattLeads\MessengerCompressBundle\Compression\ZstdCompressor;
use MattLeads\MessengerCompressBundle\Exception\CompressionException;
use MattLeads\MessengerCompressBundle\Exception\UnsupportedAlgorithmException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompressorsTest extends TestCase
{
    /** @return iterable<string, array{CompressorInterface}> */
    public static function compressors(): iterable
    {
        yield 'deflate' => [new DeflateCompressor()];
        yield 'gzip' => [new GzipCompressor()];
        if (ZstdCompressor::isAvailable()) {
            yield 'zstd' => [new ZstdCompressor()];
        }
    }

    #[DataProvider('compressors')]
    public function testRoundTripShrinksRepetitiveData(CompressorInterface $compressor): void
    {
        $data = str_repeat('{"sku":"ABC-123","qty":1,"price":9.99}', 500);
        $compressed = $compressor->compress($data);

        self::assertLessThan(\strlen($data) / 10, \strlen($compressed));
        self::assertSame($data, $compressor->decompress($compressed));
    }

    #[DataProvider('compressors')]
    public function testRoundTripOfBinaryAndEmptyData(CompressorInterface $compressor): void
    {
        foreach (['', "\x00\xff\xfe", random_bytes(4096)] as $data) {
            self::assertSame($data, $compressor->decompress($compressor->compress($data)));
        }
    }

    #[DataProvider('compressors')]
    public function testGarbageInputThrows(CompressorInterface $compressor): void
    {
        $this->expectException(CompressionException::class);
        $compressor->decompress('definitely not compressed');
    }

    public function testLevelIsHonoured(): void
    {
        $data = str_repeat('abcdefghij', 10_000) . random_bytes(2000);
        $deflate = new DeflateCompressor();
        self::assertLessThanOrEqual(\strlen($deflate->compress($data, 1)), \strlen($deflate->compress($data, 9)));
    }

    public function testRegistry(): void
    {
        $registry = new CompressorRegistry([new DeflateCompressor(), new GzipCompressor()]);

        self::assertSame(['deflate', 'gzip'], $registry->names());
        self::assertTrue($registry->has('gzip'));
        self::assertInstanceOf(GzipCompressor::class, $registry->get('gzip'));

        $this->expectException(UnsupportedAlgorithmException::class);
        $registry->get('lz4');
    }
}
