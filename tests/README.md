# Tests

A regression suite for the download pipeline fix (the "permanent hourglass" bug) and
the superadmin *Informations* modal. There is no Composer dependency and no PHPUnit —
the runner is plain PHP so it works on the production server as-is.

## Running

```
php tests/run.php              # everything
php tests/run.php Playlist     # only files whose name contains "Playlist"
php tests/run.php UiWiringTest # exactly that file
```

An exact file name beats the substring match, which is what lets one play button in the
browser run one file and nothing else.

### From the browser

A superadmin can also run the suite from **Management → Tests**
(`/superadmin/tests`). That page lists the files below with a play button each, and
`/superadmin/tests/run` streams the output live into a terminal view.

It does not include the suite in the request — `tests/bootstrap.php` refuses to run
outside the CLI SAPI. It resolves a real command-line interpreter with
`SystemCheck::resolvePhpCli()` and spawns `tests/run.php` as a child process, which
makes the page a live proof that CLI resolution works on the server. Runs are
serialised with a lock file, because two at once would share the scratch database.

The runner exits `0` when everything passed and `1` if anything failed, so it can be
dropped straight into a pre-commit hook or CI step.

Use a **CLI** PHP binary — under Windows/WAMP that is e.g.
`C:\wamp64\bin\php\php8.4.0\php.exe`, not the FPM/Apache module. Which is, fittingly,
the whole point of `PhpCliResolutionTest.php`.

## Requirements

* PHP 7.4+ with `pdo_mysql` (do not pass `-n`; skipping php.ini drops the driver).
* MySQL reachable with the credentials in `.env`. The suite creates a **separate
  scratch database** named `<DB_NAME>_test` and refuses to run any DDL unless the name
  ends in `_test`, so your real data is never touched. If MySQL is unreachable the
  database-backed cases skip rather than fail.
* No network access is needed. No test ever passes a real video id to the worker, so
  nothing is ever downloaded from YouTube.

`tests/.htaccess` denies web access to this folder. It matters: the front-controller
rewrite only fires for paths that are *not* real files, so without it every script in
here would be executable from a browser.

## What each file covers

| File | Covers |
| --- | --- |
| `PhpCliResolutionTest.php` | The root cause. `resolvePhpCli()` must return an interpreter that can actually execute a script — under PHP-FPM `PHP_BINARY` is `/usr/sbin/php-fpm8.3`, which accepts a script argument and silently ignores it. Also pins the `PHP_SAPI === 'cli'` gate, the `verifyPhpCli()` probe, the newest-first Windows fallback order, and the stdin redirect that stops an unknown binary hanging the request. |
| `DownloadWorkerTest.php` | `download_worker.php`: the usage message, rejection of shell-unsafe video ids, the CLI-only guard (proven through a real HTTP request), the `starting` marker being written before any database work, `summariseYtDlpError()`, and the fact that every outcome reaches the activity log. |
| `PlaylistDownloadStateTest.php` | `reconcileDownload()` across the full matrix (finished / empty file / spawning / live / stalled / orphaned / just queued), `markDownloadFailed()`, `markDownloadComplete()`, `retryDownload()`, `getAll()`'s key shape and self-healing, `pruneTempArtifacts()`, and `VIDEO_ID_PATTERN`. |
| `SchemaSelfHealingTest.php` | `download_failed` / `download_error` must appear in **both** the migration and `SystemCheck::checkDatabase()` — that pairing is what makes a `git pull` upgrade an existing install. Includes a functional upgrade from the pre-change schema, run twice to prove idempotency. |
| `SystemInfoTest.php` | `getYtDlpInfo()` / `getPhpInfo()`: the payload shape, strict version parsing that isn't fooled by yt-dlp's "older than 90 days" banner, that banner being reported as a notice and not an error, the `superadmin/system_info` endpoint, and its auth refusal. |
| `TestsRunnerTest.php` | The browser-facing runner itself: the `Testfile` marker contract every file here must satisfy, marker-based discovery (helpers such as `bootstrap.php` are never offered as tests), the `?file=` whitelist, the three routes and their auth, and the two spawn details that are easy to undo by accident — the child is killable, and its output is captured to files rather than pipes. No case here starts a run, or the suite would run itself. |
| `UiWiringTest.php` | The wires that break silently in a browser: route registration, controller methods, auth-before-work ordering, the admin queue's failed badge and retry button, `local_path` (not the old `local_file`), and the Informations entry living **inside** the Management dropdown. |

## Notes for anyone extending the suite

* Give every new file a marker on its second line:

  ```php
  <?php
  /**Testfile: MyThingTest*/
  ```

  The name must match the file name. That marker is how the superadmin page tells a
  runnable test from a helper, so a file without one runs from the command line but
  never appears in the browser. `TestsRunnerTest.php` fails if any file here is missing
  it.
* `download_worker.php` and `migrate_json_to_mysql.php` have top-level side effects —
  the latter runs the whole migration when `php_sapi_name() === 'cli'`. **Never
  `require` them.** Use `load_function_copy($file, $name)`, which extracts a single
  function with `token_get_all()` and `eval`s it.
* Front-end behaviour is asserted against the source, since the project has no JS test
  runner. Keep those assertions narrow: one wire each.
* Register anything you write to disk with `track_path()`; the runner deletes tracked
  paths in a `finally` block.
* `Database::__construct()` re-reads `.env` and overwrites `$_ENV`, so the singleton is
  injected via `newInstanceWithoutConstructor()` rather than reconfigured.
* `reconcileDownload()` treats `max(filemtime, ts)` as the last sign of life, so a
  fixture that needs to look stale must be backdated with `touch()`, not just given an
  old `ts`.
