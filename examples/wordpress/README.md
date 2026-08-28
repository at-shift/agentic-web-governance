# WordPress Reference Example

**Status:** Experimental, read-only, and not production-ready

**WordPress:** 7.1+

**MCP Adapter:** Official WordPress MCP Adapter 0.6.1+

This directory contains the first executable WordPress integration. It keeps
WordPress Abilities as the capability registry and the official MCP Adapter as
the MCP implementation.

## Implemented flow

```text
Authenticated MCP client
        |
        v
Official WordPress MCP Adapter
        |
        v
WP_Ability permission_callback
        |
        v
AbilityGovernanceGate
  - preserve WordPress denial
  - require an authenticated WordPress principal
  - reserve a rate budget
  - persist minimized decision evidence
        |
        v
agentic-web-governance/site-summary
```

The example Ability returns only the public site title, tagline, and home URL.
It requires the WordPress `read` capability, is explicitly exposed through
`meta.mcp.public`, and is not exposed through the Core Abilities REST API.

## Integration boundary

WordPress 7.1+ uses `wp_ability_permission_result`, which runs after the
Ability's original `permission_callback`. The gate registers at maximum filter
priority so its restriction follows ordinary extension filters.

WordPress 6.9 and 7.0 are intentionally unsupported. Keeping the first reference
path on 7.1+ avoids a second registration-time wrapper with different security
properties. Older-version compatibility can be added later as a separate
adapter if real deployment demand justifies its maintenance and test surface.

The key invariant is identical in both paths:

```text
WordPress permission is not literal true -> preserve denial
WordPress permission is literal true     -> evaluate governance
```

The official MCP Adapter performs a permission probe and then calls
`WP_Ability::execute()`, which checks permission again. The gate memoizes only a
successful decision for the same Ability, principal, and input during that PHP
request. It clears the memo at `wp_before_execute_ability`, after the final
permission check and before the callback, so one MCP invocation consumes one
rate reservation without authorizing a later execution.

Like WordPress capability checks generally, this boundary assumes installed
PHP plugins are trusted application code. A plugin that can run arbitrary PHP
or deliberately replace maximum-priority callbacks already has authority to
bypass WordPress application controls and is outside this adapter's threat
boundary.

## Storage behavior

The reference rate limiter uses transients guarded by a short option-backed
mutex. Corrupt state, lock contention, and failed writes deny the request. This
small implementation is intended to demonstrate fail-closed behavior, not to
replace an atomic high-volume counter service.

Decision and successful-execution evidence is stored in the non-autoloaded
`awg_reference_evidence` option, bounded to the latest 100 events. The store
uses an explicit field allowlist; raw Ability input and output have no storage
field. A production implementation should replace it with an append-oriented
operational store with retention, access control, and integrity guarantees.

## Try the plugin

Place the contents of [`plugin/`](plugin/) in a WordPress plugin directory named
`agentic-web-governance-reference`, activate it, and activate the official MCP
Adapter. The default MCP server can then discover the reference Ability for an
authenticated user with the `read` capability.

The repository-level boundary tests do not require WordPress:

```sh
npm run test:wordpress
```

For acceptance against actual WordPress 7.1 and the official MCP Adapter 0.6.1,
use the isolated local environment in [`e2e/`](e2e/):

```sh
npm run e2e:wordpress:setup
npm run e2e:wordpress:start
npm run e2e:wordpress:verify
npm run e2e:wordpress:stop
```

For a deployed HTTPS test site, create a least-privilege WordPress user with
the `read` capability and a dedicated, revocable Application Password. Store
the password in a mode-600 file outside the repository, then run:

```sh
AWG_WORDPRESS_URL=https://wordpress.example \
AWG_WORDPRESS_USER=awg-verifier \
AWG_WORDPRESS_APP_PASSWORD_FILE=/secure/path/application-password \
AWG_EXPECTED_SITE_NAME='Expected site name' \
AWG_EXPECTED_SITE_URL=https://wordpress.example/ \
AWG_EXPECT_RATE_LIMIT=10 \
npm run test:wordpress:live
```

`AWG_EXPECT_RATE_LIMIT` is optional. When set, the verifier intentionally
consumes that principal's complete rate window and confirms that the following
call is denied. The verifier never prints the Application Password and closes
its MCP session on completion. Revoke temporary credentials after acceptance.

If the verifier reports `rest_not_logged_in`, confirm that the web server
forwards the HTTP `Authorization` header to PHP and that any host-level REST API
access restriction is disabled or explicitly allows the verifier's IP address.
For Apache-compatible WordPress installations, the standard rewrite rule is:

```apache
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

This check is separate from WordPress security plugin settings because a
hosting platform may reject or strip the request before WordPress
authentication runs.

## Live acceptance evidence

On 2026-08-26, `npm run test:wordpress:live` completed with `ACCEPTED` against a
deployed HTTPS WordPress 7.1 installation using the official MCP Adapter 0.6.1.
The run proved unauthenticated rejection, Application Password principal
binding, MCP session initialization, Ability discovery, governed read-only
execution, and denial of the call beyond the configured rate limit.

This result satisfies the Stage 1 reference-path exit condition. It is not a
claim of production readiness, full specification conformance, or portability
beyond the WordPress adapter.

## Proven by tests

- WordPress denial remains authoritative;
- governance can narrow an allow;
- exceptions, malformed decisions, and storage failures deny;
- WordPress 7.1 rejects unsupported older integration paths;
- MCP-style duplicate permission checks reserve one budget;
- rate limits deny after the configured bound;
- evidence excludes raw input and is bounded;
- the reference Ability is read-only and MCP-only.

## Deferred

- write or destructive Abilities;
- human approval and durable pending actions;
- a permanent agent identity model;
- production evidence and rate-limit storage;
- a custom MCP transport or tool mapper;
- OAuth authorization server behavior;
- A2A and long-running tasks;
- arbitrary shell, PHP, SQL, or filesystem access.

Source files in [`plugin/`](plugin/) use the `GPL-2.0-or-later` SPDX identifier
described in [../../LICENSE.md](../../LICENSE.md).
