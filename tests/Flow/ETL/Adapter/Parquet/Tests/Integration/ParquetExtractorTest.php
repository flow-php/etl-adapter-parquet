<?php

declare(strict_types=1);

namespace Flow\ETL\Adapter\Parquet\Tests\Integration;

use function Flow\ETL\Adapter\Parquet\to_parquet;
use function Flow\ETL\DSL\from_array;
use Flow\ETL\Adapter\Parquet\ParquetExtractor;
use Flow\ETL\Extractor\Signal;
use Flow\ETL\Filesystem\Path;
use Flow\ETL\{Config, Flow, FlowContext};
use Flow\Parquet\{Options, Reader};
use PHPUnit\Framework\TestCase;

final class ParquetExtractorTest extends TestCase
{
    public function test_limit() : void
    {
        $path = \sys_get_temp_dir() . '/parquet_extractor_signal_stop.parquet';

        if (\file_exists($path)) {
            \unlink($path);
        }

        (new Flow())->read(from_array([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]))
            ->write(to_parquet($path))
            ->run();

        $extractor = new ParquetExtractor(Path::realpath($path), Options::default());
        $extractor->changeLimit(2);

        self::assertCount(
            2,
            \iterator_to_array($extractor->extract(new FlowContext(Config::default())))
        );
    }

    public function test_reading_file_from_given_offset() : void
    {
        $totalRows = (new Reader())->read(__DIR__ . '/../Fixtures/orders_flow.parquet')->metadata()->rowsNumber();

        $extractor = new ParquetExtractor(
            Path::realpath(__DIR__ . '/../Fixtures/orders_flow.parquet'),
            Options::default(),
            offset: $totalRows - 100
        );

        self::assertCount(
            100,
            \iterator_to_array($extractor->extract(new FlowContext(Config::default())))
        );
    }

    public function test_signal_stop() : void
    {
        $path = \sys_get_temp_dir() . '/parquet_extractor_signal_stop.parquet';

        if (\file_exists($path)) {
            \unlink($path);
        }

        (new Flow())->read(from_array([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]))
            ->write(to_parquet($path))
            ->run();

        $extractor = new ParquetExtractor(Path::realpath($path), Options::default());

        $generator = $extractor->extract(new FlowContext(Config::default()));

        self::assertSame([['id' => 1]], $generator->current()->toArray());
        self::assertTrue($generator->valid());
        $generator->next();
        self::assertSame([['id' => 2]], $generator->current()->toArray());
        self::assertTrue($generator->valid());
        $generator->next();
        self::assertSame([['id' => 3]], $generator->current()->toArray());
        self::assertTrue($generator->valid());
        $generator->send(Signal::STOP);
        self::assertFalse($generator->valid());
    }
}
