Guzzle Command Upgrade Guide
============================

1.x to 2.0
----------

#### PHP Version and Dependencies

Guzzle Command 2.0 requires PHP `^7.4 || ^8.0`. Guzzle Command 1.x supported
PHP `^7.2.5 || ^8.0`.

Guzzle Command 2.0 also requires Guzzle 8.x, Guzzle Promises 3.x, and Guzzle
PSR-7 3.x.

If your application still supports PHP 7.2 or 7.3, or still uses the Guzzle 7
dependency stack, continue using Guzzle Command 1.x until your minimum PHP and
dependency versions are raised.

#### Per-command HTTP Options

`GuzzleHttp\Command\ServiceClient` still reserves the `@http` command parameter
for per-command Guzzle request options. These options are passed to the
underlying Guzzle client when the command is executed.

Review the Guzzle 8 upgrade guide for request option changes, especially
stricter `proxy` option validation and the extra request argument passed to
`on_headers` callbacks.

#### Asynchronous Commands

`executeAsync()` and `executeAllAsync()` now return promises from Guzzle Promises
3.x. Code using Guzzle Promises directly should review the Guzzle Promises 3.0
upgrade guide.
