# CHANGELOG

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
