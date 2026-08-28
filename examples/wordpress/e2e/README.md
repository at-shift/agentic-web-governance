# WordPress MCP End-to-End Test

**Status:** Local acceptance environment

This environment verifies the reference path against real WordPress and MCP
implementations rather than test doubles.

## Pinned stack

- WordPress 7.1
- Official WordPress MCP Adapter 0.6.1
- PHP 8.3+
- MySQL 8.4+
- `agentic-web-governance-reference` from the current checkout

The setup script downloads the two upstream archives and checks their pinned
SHA-256 digests before extraction. Generated files, logs, database credentials,
WordPress salts, and Application Passwords stay in the ignored `runtime/`
directory.

The database is isolated as `agentic_web_governance_e2e`. Setup creates only
the dedicated `awg_e2e` database user and does not modify another WordPress
installation. The web server listens on `127.0.0.1:8081`.

## Acceptance boundary

`verify.php` passes only when all of these statements are true:

1. The running application is exactly WordPress 7.1 with MCP Adapter 0.6.1.
2. An unauthenticated MCP `initialize` request is rejected.
3. Authenticated clients can initialize a session and list the default tools.
4. MCP discovery includes `agentic-web-governance/site-summary`.
5. A capability-less WordPress user reaches the target Ability boundary but is
   denied by the Ability's own application permission.
6. An authorized user can execute the Ability and receives the expected public
   site summary.
7. Ten calls are allowed in the fixed window and the eleventh is denied by
   governance with `rate_limit_exceeded` evidence.
8. Each successful external call consumes one rate reservation even though the
   adapter checks permission more than once.
9. Evidence is bounded and contains no raw Ability input or output fields.
10. The accepted flow emits no PHP warning, notice, deprecation, parse error, or
    fatal error.

The test-only must-use plugin lowers the MCP Adapter's transport and execute
wrapper baselines from `read` to the authenticated-user pseudo-capability
`exist`. This does not grant the target Ability: it lets the E2E test prove that
the target WordPress `permission_callback` remains authoritative. The fixture
is installed only in this ignored local runtime.

## Run

MySQL must already be listening on `127.0.0.1:3306`. The harness connects as
`root` with no password by default; set `AWG_E2E_MYSQL_ROOT_PASSWORD` when
needed.
PHP must include `curl`, `mysqli`, and `pdo_mysql`. When the default `php` does
not, set `AWG_E2E_PHP` to a suitable PHP executable during setup; the selected
absolute path is recorded in the ignored runtime and reused by later commands.

```sh
npm run e2e:wordpress:setup
npm run e2e:wordpress:start
npm run e2e:wordpress:verify
npm run e2e:wordpress:stop
```

For example:

```sh
AWG_E2E_PHP=/path/to/php npm run e2e:wordpress:setup
```

`setup` and `start` are idempotent for the owned runtime. `stop` reads the PID
file and refuses to terminate a process that does not match this server.

The E2E environment is intentionally local-only. It is not a deployment recipe
and does not add compatibility paths for WordPress 6.9 or 7.0.
