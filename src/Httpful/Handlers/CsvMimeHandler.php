<?php

declare(strict_types=1);

namespace Httpful\Handlers;

use Httpful\Exception\CsvParseException;

/**
 * Mime Type: text/csv
 */
class CsvMimeHandler implements MimeHandlerInterface
{
    /**
     * @param string $body
     *
     * @throws \Exception
     *
     * @return array|null
     */
    public function parse($body)
    {
        if (empty($body)) {
            return null;
        }

        // init
        $parsed = [];

        $fp = \fopen('data://text/plain;base64,' . \base64_encode($body), 'rb');
        if ($fp === false) {
            throw new CsvParseException('Unable to parse response as CSV');
        }

        while (($r = \fgetcsv($fp)) !== false) {
            $parsed[] = $r;
        }

        if (empty($parsed)) {
            throw new CsvParseException('Unable to parse response as CSV');
        }

        return $parsed;
    }

    /**
     * @param mixed $payload
     *
     * @return false|string
     */
    public function serialize($payload)
    {
        $fp = \fopen('php://temp/maxmemory:' . (6 * 1024 * 1024), 'r+b');
        if ($fp === false) {
            throw new CsvParseException('Unable to parse response as CSV');
        }

        $i = 0;

        foreach ($payload as $fields) {
            if ($i++ === 0) {
                \fputcsv($fp, \array_keys($fields));
            }
            \fputcsv($fp, $fields);
        }

        \rewind($fp);
        $data = \stream_get_contents($fp);
        \fclose($fp);

        return $data;
    }
}
