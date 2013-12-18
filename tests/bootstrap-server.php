<?php

// Command that starts the built-in web server
$command = sprintf('php -S %s:%d -t %s >/dev/null 2>&1 & echo $!', WEB_SERVER_HOST, WEB_SERVER_PORT, WEB_SERVER_DOCROOT);

// Execute the command and store the process ID
$output = array();
exec($command, $output);
// sleep for a second to let server come up
sleep(1);
$pid = (int) $output[0];

echo sprintf('%s - Web server started on %s:%d with PID %d', date('r'), WEB_SERVER_HOST, WEB_SERVER_PORT, $pid) . PHP_EOL;

// More bootstrap code
