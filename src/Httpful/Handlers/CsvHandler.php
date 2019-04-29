<?php

declare(strict_types=1);

/**
 * Mime Type: text/csv
 */
namespace Httpful\Handlers;

/**
 * Class CsvHandler
 */
class CsvHandler extends DefaultHandler
{
    /**
     * @param string $body
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function parse($body)
    {
        if (empty($body)) {
            return null;
        }

        $parsed = [];
        $fp = \fopen('data://text/plain;base64,' . \base64_encode($body), 'rb');
        if ($fp === false) {
            throw new \Exception('Unable to parse response as CSV');
        }

        while (($r = \fgetcsv($fp)) !== false) {
            $parsed[] = $r;
        }

        if (empty($parsed)) {
            throw new \Exception('Unable to parse response as CSV');
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
            throw new \Exception('Unable to parse response as CSV');
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
