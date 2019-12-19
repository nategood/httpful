<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Message\StreamInterface;
use voku\helper\UTF8;

class Stream implements StreamInterface
{
    /**
     * Resource modes.
     *
     * @var string
     *
     * @see http://php.net/manual/function.fopen.php
     * @see http://php.net/manual/en/function.gzopen.php
     */
    const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';

    /**
     * @var array<string, array<string, bool>> Hash of readable and writable stream types
     */
    const READ_WRITE_HASH = [
        'read' => [
            'r'   => true,
            'w+'  => true,
            'r+'  => true,
            'x+'  => true,
            'c+'  => true,
            'rb'  => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'rt'  => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a+'  => true,
        ],
        'write' => [
            'w'   => true,
            'w+'  => true,
            'rw'  => true,
            'r+'  => true,
            'x+'  => true,
            'c+'  => true,
            'wb'  => true,
            'w+b' => true,
            'r+b' => true,
            'x+b' => true,
            'c+b' => true,
            'w+t' => true,
            'r+t' => true,
            'x+t' => true,
            'c+t' => true,
            'a'   => true,
            'a+'  => true,
        ],
    ];

    /**
     * @var string
     */
    const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    /**
     * @var resource|null
     */
    private $stream;

    /**
     * @var int|null
     */
    private $size;

    /**
     * @var bool
     */
    private $seekable;

    /**
     * @var bool
     */
    private $readable;

    /**
     * @var bool
     */
    private $writable;

    /**
     * @var string|null
     */
    private $uri;

    /**
     * @var array
     */
    private $customMetadata;

    /**
     * @var bool
     */
    private $serialized;

    /**
     * This constructor accepts an associative array of options.
     *
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknowledge, then you can
     *   provide that size, in bytes.
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     *
     * @param resource            $stream  stream resource to wrap
     * @param array<string,mixed> $options associative array of options
     *
     * @throws \InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct($stream, $options = [])
    {
        if (!\is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        if (isset($options['size'])) {
            $this->size = (int) $options['size'];
        }

        $this->customMetadata = $options['metadata'] ?? [];

        $this->serialized = $options['serialized'] ?? false;

        $this->stream = $stream;
        $meta = \stream_get_meta_data($this->stream);
        $this->seekable = (bool) $meta['seekable'];
        $this->readable = (bool) \preg_match(self::READABLE_MODES, $meta['mode']);
        $this->writable = (bool) \preg_match(self::WRITABLE_MODES, $meta['mode']);
        $this->uri = $this->getMetadata('uri');
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            $this->seek(0);

            if ($this->stream === null) {
                return '';
            }

            return (string) \stream_get_contents($this->stream);
        } catch (\Exception $e) {
            return '';
        }
    }

    public function close()
    {
        if (isset($this->stream)) {
            if (\is_resource($this->stream)) {
                \fclose($this->stream);
            }

            /** @noinspection UnusedFunctionResultInspection */
            $this->detach();
        }
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        $this->stream = null;
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $result;
    }

    /**
     * @return bool
     */
    public function eof(): bool
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        return \feof($this->stream);
    }

    /**
     * @return mixed
     */
    public function getContents()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $contents = \stream_get_contents($this->stream);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        if ($this->serialized) {
            /** @noinspection UnserializeExploitsInspection */
            $contents = \unserialize($contents, []);
        }

        return $contents;
    }

    /**
     * @param string|null $key
     *
     * @return array|mixed|null
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        }

        if (!$key) {
            /** @noinspection AdditionOperationOnArraysInspection */
            return $this->customMetadata + \stream_get_meta_data($this->stream);
        }

        if (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }

        $meta = \stream_get_meta_data($this->stream);

        return $meta[$key] ?? null;
    }

    /**
     * @return int|null
     */
    public function getSize()
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            \clearstatcache(true, $this->uri);
        }

        $stats = \fstat($this->stream);
        if ($stats !== false && isset($stats['size'])) {
            $this->size = $stats['size'];

            return $this->size;
        }

        return null;
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * @return bool
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function read($length): string
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        if ($length === 0) {
            return '';
        }

        $string = \fread($this->stream, $length);
        if ($string === false) {
            throw new \RuntimeException('Unable to read from stream');
        }

        return $string;
    }

    /**
     * @return void
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * @param int $offset
     * @param int $whence
     *
     * @return void
     */
    public function seek($offset, $whence = \SEEK_SET)
    {
        $whence = (int) $whence;

        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        }
        if (\fseek($this->stream, $offset, $whence) === -1) {
            throw new \RuntimeException(
                'Unable to seek to stream position '
                . $offset . ' with whence ' . \var_export($whence, true)
            );
        }
    }

    /**
     * @return int
     */
    public function tell(): int
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $result = \ftell($this->stream);
        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    /**
     * @param string $string
     *
     * @return int
     */
    public function write($string): int
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->writable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result = \fwrite($this->stream, $string);

        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * Creates a new PSR-7 stream.
     *
     * @param mixed $body
     *
     * @return StreamInterface|null
     */
    public static function create($body = '')
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }

        if ($body === null) {
            $body = '';
            $serialized = false;
        } elseif (\is_numeric($body)) {
            $body = (string) $body;
            $serialized = UTF8::is_serialized($body);
        } elseif (
            \is_array($body)
            ||
            $body instanceof \Serializable
        ) {
            $body = \serialize($body);
            $serialized = true;
        } else {
            $serialized = false;
        }

        if (\is_string($body)) {
            $resource = \fopen('php://temp', 'rwb+');
            if ($resource !== false) {
                \fwrite($resource, $body);
                $body = $resource;
            }
        }

        if (\is_resource($body)) {
            $new = new static($body);
            if ($new->stream === null) {
                return null;
            }

            $meta = \stream_get_meta_data($new->stream);
            $new->serialized = $serialized;
            $new->seekable = $meta['seekable'];
            $new->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
            $new->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);
            $new->uri = $new->getMetadata('uri');

            return $new;
        }

        return null;
    }

    /**
     * @param mixed $body
     *
     * @return StreamInterface
     */
    public static function createNotNull($body = ''): StreamInterface
    {
        $stream = static::create($body);
        if ($stream === null) {
            $stream = static::create();
        }

        \assert($stream instanceof self);

        return $stream;
    }
}
