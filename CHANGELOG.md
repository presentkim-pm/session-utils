# Changelog

## 2.0.0 (2026-11-27)

The Poggit virion version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/session-utils/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) and **PHP 8.4+**.
- Distributed with Composer: the Poggit virion files (`.poggit.yml`, `virion.yml`) are removed.
- A closed `SessionManager` throws `LogicException` from `createSession()` and `getOrCreateSession()`.
- Termination order is now consistent: `Session::onTerminate()` runs first, then the `onSessionTerminated` callback,
  each exactly once.
- A session whose start fails is terminated with `START_FAILED`, the error is logged and `createSession()` returns `null`.
  `onSessionCreated` is not called for it.
- `SessionSequence::start()` and `startFrom()` terminate the player's session in any other step with `RESTART`.
- `SequenceSession::next()` does nothing when the session is no longer active.

### ✨ Features
- `SessionManager::close()` / `isClosed()`: terminates every session and detaches all listeners.
- The manager closes itself when its owning plugin is disabled, so the manual
  `terminateAll(SessionTerminateReasons::PLUGIN_DISABLE)` in `onDisable()` is no longer necessary.
- Lifecycle sessions are also created for the players already online when the manager is constructed
  (late enable, reload).
- `SessionSequence::getCurrentStep(Player $player)`: the 0-based index of the player's current step, or `null`.
- `SessionSequence::close()`: closes the manager of every step. It also happens when the plugin is disabled.
- `onSessionCreated` / `onSessionTerminated` callbacks may type their session parameter with the concrete session class.
- Event listeners are registered per plugin, so a single Composer copy of the library can be shared by several plugins:
  disabling one plugin no longer silences the sessions of the others.

### 🐛 Fixes
- Removing a stale `Session` object (one that was already replaced by a newer session of the same player) is now a no-op
  and no longer removes the current session.
- An error while logging a failure in `removeSession()` no longer crashes.

### 🧪 Tests
- PHPUnit tests (32 tests) against the real `axolotl-pm/pocketmine-mp` code, and GitHub Actions CI on PHP 8.4 / 8.5.


## 1.1.0 (2026-03-21)

### ✨ Features
- `SessionSequence`: ordered progression of `SequenceSession` subclasses for one player, with `start()`,
  `startFrom()` (0-based index or class-string), `onComplete()` and `terminateAll()`.
- `SequenceSession`: base class for steps of a sequence, with `next()` to advance to the next step or invoke the
  `onComplete` callback on the last one.
- `SequenceSessionManager`: internal `SessionManager` subclass that chains steps with `setNext()`, `setOnExhausted()`
  and `progressNext()`.
- `SessionManager::getSessionOrThrow()`: the active session, or `RuntimeException` if none exists.
- `SessionManager::getOrCreateSession()`: the active session, or a newly created one.
- `SessionManager::onSessionCreated()` / `onSessionTerminated()`: callbacks after a session is created and started /
  terminated and removed.
- `ManagerMethodDispatcher`: dispatcher that invokes a method on a `SessionManager`, used for join/quit handling.

### 🔄 Changes
- The `Session` interface and `AbstractSession` class are merged into one abstract `Session` class.
  The session holds its `SessionManager`, so `terminate()` can remove itself without outside access to the manager.
- `SessionEventDispatcher` is renamed to `BaseSessionEventDispatcher` (abstract); the method dispatch logic moved to
  `SessionMethodDispatcher`.
- Join/quit handling goes through `SessionEventListenerRegistry` via `ManagerMethodDispatcher`, so several managers
  no longer register duplicate PMMP listeners for the same event.
- `SessionManager` construction is split into `collectSessionLifecycleBindings()` and `collectSessionEventBindings()`.

### 🐛 Fixes
- `SessionManager::removeSession()` could remove a session twice when `Session::terminate()` called back into it.
  Sessions are now unregistered before `terminate()` is invoked.


## 1.0.0

- Initial release.
