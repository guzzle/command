=========
Changelog
=========

0.6.0 (2014-08-08)
------------------

* Added a Debug subscriber that can be used to trace through the lifecycle of
  a command and how it is modified in each event.

0.5.0 (2014-08-01)
------------------

* Rewrote event system so that all exceptions encountered during the transfer
  of a command are emitted to the "error" event.
* No longer wrapping exceptions thrown during the execution of a command.
* Added the ability to get a CommandTransaction from events and updating
  classes to use a CommandTransaction rather than many constructor arguments.
* Fixed an issue with sending many commands in parallel
* Added ``batch()`` to ServiceClientInterface for sending commands in batches
* Added subscriber to easily mock commands results
