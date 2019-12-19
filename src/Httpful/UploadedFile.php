<?php

declare(strict_types=1);

namespace Httpful;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class UploadedFile implements UploadedFileInterface
{
    /**
     * @var array
     */
    const ERRORS = [
        \UPLOAD_ERR_OK         => 1,
        \UPLOAD_ERR_INI_SIZE   => 1,
        \UPLOAD_ERR_FORM_SIZE  => 1,
        \UPLOAD_ERR_PARTIAL    => 1,
        \UPLOAD_ERR_NO_FILE    => 1,
        \UPLOAD_ERR_NO_TMP_DIR => 1,
        \UPLOAD_ERR_CANT_WRITE => 1,
        \UPLOAD_ERR_EXTENSION  => 1,
    ];

    /**
     * @var string|null
     */
    private $clientFilename;

    /**
     * @var string|null
     */
    private $clientMediaType;

    /**
     * @var int
     */
    private $error;

    /**
     * @var string|null
     */
    private $file;

    /**
     * @var bool
     */
    private $moved = false;

    /**
     * @var int
     */
    private $size;

    /**
     * @var StreamInterface|null
     */
    private $stream;

    /**
     * @param resource|StreamInterface|string $streamOrFile
     * @param int                             $size
     * @param int                             $errorStatus
     * @param string|null                     $clientFilename
     * @param string|null                     $clientMediaType
     */
    public function __construct(
        $streamOrFile,
        $size,
        $errorStatus,
        $clientFilename = null,
        $clientMediaType = null
    ) {
        if (
            \is_int($errorStatus) === false
            ||
            !isset(self::ERRORS[$errorStatus])
        ) {
            throw new \InvalidArgumentException('Upload file error status must be an integer value and one of the "UPLOAD_ERR_*" constants.');
        }

        if (\is_int($size) === false) {
            throw new \InvalidArgumentException('Upload file size must be an integer');
        }

        if (
            $clientFilename !== null
            &&
            !\is_string($clientFilename)
        ) {
            throw new \InvalidArgumentException('Upload file client filename must be a string or null');
        }

        if (
            $clientMediaType !== null
            &&
            !\is_string($clientMediaType)
        ) {
            throw new \InvalidArgumentException('Upload file client media type must be a string or null');
        }

        $this->error = $errorStatus;
        $this->size = $size;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if ($this->error === \UPLOAD_ERR_OK) {
            // Depending on the value set file or stream variable.
            if (\is_string($streamOrFile)) {
                $this->file = $streamOrFile;
            } elseif (\is_resource($streamOrFile)) {
                $this->stream = Stream::create($streamOrFile);
            } elseif ($streamOrFile instanceof StreamInterface) {
                $this->stream = $streamOrFile;
            } else {
                throw new \InvalidArgumentException('Invalid stream or file provided for UploadedFile');
            }
        }
    }

    /**
     * @return string|null
     */
    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    /**
     * @return string|null
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }

    /**
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return StreamInterface
     */
    public function getStream(): StreamInterface
    {
        $this->_validateActive();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        if ($this->file !== null) {
            $resource = \fopen($this->file, 'rb');
        } else {
            $resource = '';
        }

        return Stream::createNotNull($resource);
    }

    /**
     * @param string $targetPath
     *
     * @return void
     */
    public function moveTo($targetPath)
    {
        $this->_validateActive();

        if (
            !\is_string($targetPath)
            ||
            $targetPath === ''
        ) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        if ($this->file !== null) {
            $this->moved = 'cli' === \PHP_SAPI ? \rename($this->file, $targetPath) : \move_uploaded_file($this->file, $targetPath);
        } else {
            $stream = $this->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            // Copy the contents of a stream into another stream until end-of-file.
            $dest = Stream::createNotNull(\fopen($targetPath, 'wb'));
            while (!$stream->eof()) {
                if (!$dest->write($stream->read(1048576))) {
                    break;
                }
            }

            $this->moved = true;
        }

        if ($this->moved === false) {
            throw new \RuntimeException(\sprintf('Uploaded file could not be moved to %s', $targetPath));
        }
    }

    /**
     * @throws \RuntimeException if is moved or not ok
     *
     * @return void
     */
    private function _validateActive()
    {
        if ($this->error !== \UPLOAD_ERR_OK) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }
}
