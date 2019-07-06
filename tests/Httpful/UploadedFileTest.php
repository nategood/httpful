<?php

declare(strict_types=1);

namespace Httpful\tests;

use Httpful\Factory;
use Httpful\Stream;
use Httpful\UploadedFile;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class UploadedFileTest extends TestCase
{
    /**
     * @var array
     */
    private $cleanup = [];

    protected function setUp()
    {
        $this->cleanup = [];
    }

    protected function tearDown()
    {
        foreach ($this->cleanup as $file) {
            if (\is_string($file) && \file_exists($file)) {
                \unlink($file);
            }
        }
    }

    /**
     * @return array
     */
    public function invalidStreams(): array
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'int'    => [1],
            'float'  => [1.1],
            'array'  => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalidStreams
     *
     * @param mixed $streamOrFile
     */
    public function testRaisesExceptionOnInvalidStreamOrFile($streamOrFile)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid stream or file provided for UploadedFile');

        new UploadedFile($streamOrFile, 0, \UPLOAD_ERR_OK);
    }

    /**
     * @return array
     */
    public function invalidErrorStatuses(): array
    {
        return [
            'null'     => [null],
            'true'     => [true],
            'false'    => [false],
            'float'    => [1.1],
            'string'   => ['1'],
            'array'    => [[1]],
            'object'   => [(object) [1]],
            'negative' => [-1],
            'too-big'  => [9],
        ];
    }

    /**
     * @dataProvider invalidErrorStatuses
     *
     * @param mixed $status
     */
    public function testRaisesExceptionOnInvalidErrorStatus($status)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('status');

        new UploadedFile(\fopen('php://temp', 'wb+'), 0, $status);
    }

    /**
     * @return array
     */
    public function invalidFilenamesAndMediaTypes(): array
    {
        return [
            'true'   => [true],
            'false'  => [false],
            'int'    => [1],
            'float'  => [1.1],
            'array'  => [['string']],
            'object' => [(object) ['string']],
        ];
    }

    /**
     * @dataProvider invalidFilenamesAndMediaTypes
     *
     * @param mixed $filename
     */
    public function testRaisesExceptionOnInvalidClientFilename($filename)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('filename');

        new UploadedFile(\fopen('php://temp', 'wb+'), 0, \UPLOAD_ERR_OK, $filename);
    }

    /**
     * @dataProvider invalidFilenamesAndMediaTypes
     *
     * @param mixed $mediaType
     */
    public function testRaisesExceptionOnInvalidClientMediaType($mediaType)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('media type');

        new UploadedFile(\fopen('php://temp', 'wb+'), 0, \UPLOAD_ERR_OK, 'foobar.baz', $mediaType);
    }

    public function testGetStreamReturnsOriginalStreamObject()
    {
        $stream = Stream::create('');
        $upload = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);

        static::assertSame($stream, $upload->getStream());
    }

    public function testGetStreamReturnsWrappedPhpStream()
    {
        $stream = \fopen('php://temp', 'wb+');
        $upload = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);
        $uploadStream = $upload->getStream()->detach();

        static::assertSame($stream, $uploadStream);
    }

    public function testGetStream()
    {
        $upload = new UploadedFile(__DIR__ . '/../static/foo.txt', 0, \UPLOAD_ERR_OK);
        $stream = $upload->getStream();
        static::assertInstanceOf(StreamInterface::class, $stream);
        static::assertEquals("Foobar\n", $stream->__toString());
    }

    public function testSuccessful()
    {
        $stream = Stream::create('Foo bar!');
        $upload = new UploadedFile($stream, $stream->getSize(), \UPLOAD_ERR_OK, 'filename.txt', 'text/plain');

        static::assertEquals($stream->getSize(), $upload->getSize());
        static::assertEquals('filename.txt', $upload->getClientFilename());
        static::assertEquals('text/plain', $upload->getClientMediaType());

        $to = \tempnam(\sys_get_temp_dir(), 'successful');
        $this->cleanup[] = $to;
        $upload->moveTo($to);
        static::assertFileExists($to);
        static::assertEquals($stream->__toString(), \file_get_contents($to));
    }

    /**
     * @return array
     */
    public function invalidMovePaths(): array
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'int'    => [1],
            'float'  => [1.1],
            'empty'  => [''],
            'array'  => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalidMovePaths
     *
     * @param mixed $path
     */
    public function testMoveRaisesExceptionForInvalidPath($path)
    {
        $stream = (new Factory())->createStream('Foo bar!');
        $upload = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);

        $this->cleanup[] = $path;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path');
        $upload->moveTo($path);
    }

    public function testMoveCannotBeCalledMoreThanOnce()
    {
        $stream = (new Factory())->createStream('Foo bar!');
        $upload = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);

        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        static::assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->moveTo($to);
    }

    public function testCannotRetrieveStreamAfterMove()
    {
        $stream = (new Factory())->createStream('Foo bar!');
        $upload = new UploadedFile($stream, 0, \UPLOAD_ERR_OK);

        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        static::assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        /** @noinspection UnusedFunctionResultInspection */
        $upload->getStream();
    }

    /**
     * @return array
     */
    public function nonOkErrorStatus(): array
    {
        return [
            'UPLOAD_ERR_INI_SIZE'   => [\UPLOAD_ERR_INI_SIZE],
            'UPLOAD_ERR_FORM_SIZE'  => [\UPLOAD_ERR_FORM_SIZE],
            'UPLOAD_ERR_PARTIAL'    => [\UPLOAD_ERR_PARTIAL],
            'UPLOAD_ERR_NO_FILE'    => [\UPLOAD_ERR_NO_FILE],
            'UPLOAD_ERR_NO_TMP_DIR' => [\UPLOAD_ERR_NO_TMP_DIR],
            'UPLOAD_ERR_CANT_WRITE' => [\UPLOAD_ERR_CANT_WRITE],
            'UPLOAD_ERR_EXTENSION'  => [\UPLOAD_ERR_EXTENSION],
        ];
    }

    /**
     * @dataProvider nonOkErrorStatus
     *
     * @param mixed $status
     */
    public function testConstructorDoesNotRaiseExceptionForInvalidStreamWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        static::assertSame($status, $uploadedFile->getError());
    }

    /**
     * @dataProvider nonOkErrorStatus
     *
     * @param mixed $status
     */
    public function testMoveToRaisesExceptionWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploadedFile->moveTo(__DIR__ . '/' . \uniqid('', true));
    }

    /**
     * @dataProvider nonOkErrorStatus
     *
     * @param mixed $status
     */
    public function testGetStreamRaisesExceptionWhenErrorStatusPresent($status)
    {
        $uploadedFile = new UploadedFile('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        /** @noinspection UnusedFunctionResultInspection */
        $uploadedFile->getStream();
    }

    public function testMoveToCreatesStreamIfOnlyAFilenameWasProvided()
    {
        $this->cleanup[] = $from = \tempnam(\sys_get_temp_dir(), 'copy_from');
        $this->cleanup[] = $to = \tempnam(\sys_get_temp_dir(), 'copy_to');

        \copy(__FILE__, $from);

        $uploadedFile = new UploadedFile($from, 100, \UPLOAD_ERR_OK, \basename($from), 'text/plain');
        $uploadedFile->moveTo($to);

        static::assertFileEquals(__FILE__, $to);
    }
}
