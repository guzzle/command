# CHANGELOG

## 1.3.0 - 2023-05-21

* Add support for `guzzlehttp/promises` 2.x
* Replace deprecated promise helper calls
* Add PHP-CS-Fixer static analysis
* Apply Symfony code style

## 1.2.3 - 2023-04-18

* Add PHP 8.2 CI coverage
* Bump minimum Guzzle dependency versions
* Add Composer Normalize checks
* Refresh GitHub Actions configuration

## 1.2.2 - 2022-02-08

* Fix PHP 8.1 return type deprecation notices
* Bump minimum Guzzle dependency versions
* Improve README documentation

## 1.2.1 - 2021-09-05

* Add PHP 8.1 test support
* Update package metadata and license text
* Add security and funding documentation

## 1.2.0 - 2021-08-14

* Add PHP 8.0 support
* Update dependency constraints for Guzzle 7.3
* Add support for PSR-7 2.x
* Replace Travis CI with GitHub Actions
* Simplify the test Makefile

## 1.1.0 - 2020-09-28

* Update to Guzzle 7
* Raise the minimum PHP version to 7.2.5
* Update tests to PHPUnit 8

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

* Add support for intercepting command error events
* Refactor command event handling
* Improve error context handling
* Add an emitter option to `AbstractClient`

## 0.3.0 - 2014-04-02

* Add custom metadata support to `CommandErrorEvent`

## 0.2.0 - 2014-03-30

* Add `AbstractClient` as a base service client implementation
* Add magic command methods
* Add parallel command execution
* Update Composer requirements
* Fix an undefined offset issue

## 0.1.0 - 2014-03-15

* Initial release
