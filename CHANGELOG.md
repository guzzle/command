# CHANGELOG

## 2.0.1 - UPCOMING

* Add support for PHP 8.6
* Require `guzzlehttp/guzzle` ^8.1, `guzzlehttp/promises` ^3.0.2, and `guzzlehttp/psr7` ^3.1

## 2.0.0 - 2026-07-20

* Drop support for PHP 7.2 and 7.3
* Require `guzzlehttp/guzzle` ^8.0, `guzzlehttp/promises` ^3.0, and `guzzlehttp/psr7` ^3.0
* Escape unsafe command names and previous messages in generated command exceptions
* Reject native PHP serialization of `ServiceClient`
* Require custom implementations and subclasses of public command APIs to match native method signatures
* Require service client response transformers to return `ResultInterface` values
* Require command collections to be iterable and reject single commands in `executeAll()` and `executeAllAsync()`
* Require custom middleware, transformers, and `executeAll()` callbacks to accept exact argument types instead of relying on scalar coercion
* Redact credential-bearing command and HTTP arguments from PHP 8.2+ backtraces
* Preserve requests from PSR-18 request and network exceptions and from Guzzle transfer exceptions when wrapping command failures
* Allow command exception constructors to accept any previous `Throwable`
* Add generic PHPDoc return types to asynchronous service client APIs
* Improve PHPDoc for command handler stacks, transformer callables, and concurrent command callbacks

## 1.5.3 - 2026-07-17

* Require `guzzlehttp/guzzle` ^7.15 and `guzzlehttp/psr7` ^2.13

## 1.5.2 - 2026-07-08

* Require `guzzlehttp/guzzle` ^7.13.3, `guzzlehttp/promises` ^2.5.1, and `guzzlehttp/psr7` ^2.12.4

## 1.5.1 - 2026-06-23

* Require `guzzlehttp/guzzle` ^7.12.3 and `guzzlehttp/psr7` ^2.12.3

## 1.5.0 - 2026-06-02

* Require `guzzlehttp/guzzle` ^7.11, `guzzlehttp/promises` ^2.5, and `guzzlehttp/psr7` ^2.11
* Deprecate non-iterable command collections

## 1.4.0 - 2026-05-18

* Add PHP 8.5 support
* Require `guzzlehttp/guzzle` ^7.10, `guzzlehttp/promises` ^2.3, and `guzzlehttp/psr7` ^2.8
* Remove stale `createPool` references from `ServiceClientInterface` documentation

## 1.3.2 - 2025-02-04

* Add PHP 8.4 support

## 1.3.1 - 2023-12-03

* Add PHP 8.3 support

## 1.3.0 - 2023-05-21

* Add support for `guzzlehttp/promises` 2.x
* Replace deprecated promise helper calls

## 1.2.3 - 2023-04-18

* Add PHP 8.2 support
* Bump minimum Guzzle dependency versions

## 1.2.2 - 2022-02-08

* Fix PHP 8.1 return type deprecation notices
* Bump minimum Guzzle dependency versions

## 1.2.1 - 2021-09-05

* Add PHP 8.1 support
* Update package metadata and license text

## 1.2.0 - 2021-08-14

* Add PHP 8.0 support
* Update dependency constraints for Guzzle 7.3
* Add support for PSR-7 2.x

## 1.1.0 - 2020-09-28

* Update to Guzzle 7
* Raise the minimum PHP version to 7.2.5

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
