# CHANGELOG

## 1.3.0 - 2023-05-21

* Added support for `guzzlehttp/promises` 2.x.
* Replaced deprecated promise helper calls with `Promise\Create::iterFor()` and
  `Promise\Coroutine::of()`.
* Added PHP-CS-Fixer static analysis and applied Symfony-style formatting
  updates.

## 1.2.3 - 2023-04-18

* Added PHP 8.2 CI coverage.
* Bumped minimum supported Guzzle dependency versions.
* Added Composer Normalize checks and refreshed GitHub Actions configuration.

## 1.2.2 - 2022-02-08

* Fixed PHP 8.1 return type deprecation notices in data container methods.
* Bumped minimum supported Guzzle dependency versions.
* Improved README documentation for service client setup and usage.

## 1.2.1 - 2021-09-05

* Added PHP 8.1 test support.
* Updated package metadata, license text, security documentation, and funding
  metadata.

## 1.2.0 - 2021-08-14

* Added PHP 8.0 support.
* Updated dependency constraints for Guzzle 7.3, Promises 1.x, and PSR-7 1.x /
  2.x.
* Replaced Travis CI with GitHub Actions and simplified the test Makefile.

## 1.1.0 - 2020-09-28

* Updated to Guzzle 7.
* Raised the minimum PHP version to 7.2.5.
* Updated tests to PHPUnit 8.

## 1.0.0 - 2016-11-24

* Add badges to README.md
* Switch README from .rst to .md format 
* Update dependencies
* Add command to handler call to provide support for GuzzleServices

## 0.9.0 - 2016-01-30

* Updated to use Guzzle 6 and PSR-7.
* Event system has been replaced with a middleware system
    * Middleware at the command layer work the same as middleware from the
      HTTP layer, but work with `Command` and `Result` objects instead of
      `Request` and `Response` objects
    * The command middleware is in a separate `HandlerStack` instance than the
      HTTP middleware.
* `Result` objects are the result of executing a `Command` and are used to hold
  the parsed response data.
* Asynchronous code now uses the `guzzlehttp/promises` package instead of 
  `guzzlehttp/ringphp`, which means that asynchronous results are implemented
  as Promises/A+ compliant `Promise` objects, instead of futures.
* The existing `Subscriber`s were removed.
* The `ServiceClientInterface` and `ServiceClient` class now provide the basic
  foundation of a web service client.

## 0.8.0 - 2015-02-02

* Removed `setConfig` from `ServiceClientInterface`.
* Added `initTransaction` to `ServiceClientInterface`.

## 0.7.1 - 2015-01-14

* Fixed and issue where intercepting commands encapsulated by a
  CommandToRequestIterator could lead to deep recursion. These commands are
  now skipped and the iterator moves to the next element using a `goto`
  statement.

## 0.7.0 - 2014-10-12

* Updated to use Guzzle 5, and added support for asynchronous results.
* Renamed `prepare` event to `prepared`.
* Added `init` event.

## 0.6.0 - 2014-08-08

* Added a Debug subscriber that can be used to trace through the lifecycle of
  a command and how it is modified in each event.

## 0.5.0 - 2014-08-01

* Rewrote event system so that all exceptions encountered during the transfer
  of a command are emitted to the "error" event.
* No longer wrapping exceptions thrown during the execution of a command.
* Added the ability to get a CommandTransaction from events and updating
  classes to use a CommandTransaction rather than many constructor arguments.
* Fixed an issue with sending many commands in parallel
* Added `batch()` to ServiceClientInterface for sending commands in batches
* Added subscriber to easily mock commands results

## 0.4.0 - 2014-04-29

* Added support for intercepting command error events with a result before
  sending a request.
* Refactored command event handling and error context handling.
* Added an emitter option to `AbstractClient`.

## 0.3.0 - 2014-04-02

* Added custom metadata support to `CommandErrorEvent`.

## 0.2.0 - 2014-03-30

* Added `AbstractClient` as a base service client implementation.
* Added support for command execution, magic command methods, exception wrapping,
  and parallel command execution.
* Updated Composer requirements and fixed an undefined offset issue.

## 0.1.0 - 2014-03-15

* Initial release of `guzzlehttp/command`.
* Added command, service client, event, request location, response location, and
  model abstractions.
* Added Composer, PHPUnit, Travis CI, tests, and README documentation.
