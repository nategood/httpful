<?php

declare(strict_types=1);

$php_version = \PHP_VERSION;
$php_major = (float) \substr($php_version, 0, 3);

// Define SIGKILL if pcntl is not found
if (!\function_exists('pcntl_signal')) {
    \define('SIGKILL', 9);
}

if ($php_major < 5.4 || \stripos(\PHP_OS, 'WIN') === 0) {
    \define('WITHOUT_SERVER', true);
} else {
    // Command that starts the built-in web server
    $serverLogFile = './server.log';
    \touch($serverLogFile);
    /** @noinspection PhpUndefinedConstantInspection */
    $command = \sprintf('php -S %s:%d -t %s > ' . $serverLogFile . ' 2>&1 & echo $!', WEB_SERVER_HOST, WEB_SERVER_PORT, WEB_SERVER_DOCROOT);

    // Execute the command and store the process ID
    $output = [];
    \exec($command, $output, $exit_code);

    // sleep for a second to let server come up
    \sleep(1);
    $pid = (int) $output[0];

    // check server.log to see if it failed to start
    $serverLogData = \file_get_contents($serverLogFile);
    if (\strpos($serverLogData, 'Fail') !== false) {
        // server failed to start for some reason
        echo 'Failed to start server! Logs:' . \PHP_EOL . \PHP_EOL;
        /** @noinspection ForgottenDebugOutputInspection */
        \print_r($serverLogData);
        exit(1);
    }

    /** @noinspection PhpUndefinedConstantInspection */
    echo \sprintf('%s - Web server started on %s:%d with PID %d', \date('r'), WEB_SERVER_HOST, WEB_SERVER_PORT, $pid) . \PHP_EOL;

    \register_shutdown_function(static function () {
        // cleanup after ourselves -- remove log file, shut down server
        global $pid;
        \unlink('./server.log');
        \posix_kill($pid, \SIGKILL);
    });
}
