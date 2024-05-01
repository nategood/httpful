<?php
/**
 * Mime Type: text/csv
 * @author Raja Kapur <rajak@twistedthrottle.com>
 */

namespace Httpful\Handlers;

class CsvHandler extends MimeHandlerAdapter
{
    /**
     * @param string $body
     * @return mixed
     * @throws \Exception
     */
    public function parse($body)
    {
        if (empty($body))
            return null;

        $parsed = [];
        $fp = fopen('data://text/plain;base64,' . base64_encode($body), 'r');
        while (($r = fgetcsv($fp)) !== FALSE) {
            $parsed[] = $r;
        }

        if ($parsed === [])
            throw new \Exception("Unable to parse response as CSV");
        return $parsed;
    }

    /**
     * @param mixed $payload
     * @return string
     */
    public function serialize($payload): string
    {
        $fp = fopen('php://temp/maxmemory:'. (6*1024*1024), 'r+');
        $i = 0;
        foreach ($payload as $fields) {
            if($i++ == 0) {
                fputcsv($fp, array_keys($fields));
            }
            fputcsv($fp, $fields);
        }
        rewind($fp);
        $data = stream_get_contents($fp);
        fclose($fp);
        return $data;
    }
}
