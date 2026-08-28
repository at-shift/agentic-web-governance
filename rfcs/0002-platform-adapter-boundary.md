# RFC 0002: Platform Adapter Boundary

- **Status:** Accepted
- **Authors:** `@at-shift`
- **Created:** 2026-08-26
- **Updated:** 2026-08-26
- **Target version:** Draft 0.2
- **Supersedes:** None
- **Superseded by:** None

## Summary

This RFC keeps Agentic Web Governance platform-neutral while allowing one
platform implementation to advance first. It defines an application-adapter
boundary, keeps platform-native authorization authoritative, and separates an
accepted portability design from a demonstrated portability claim.

WordPress remains the first reference application adapter. The project will not
claim implementation-level portability until a materially distinct,
non-WordPress adapter preserves the same core invariants and passes the same
protocol-neutral conformance fixtures.

## Decision

Accepted by `@at-shift` on 2026-08-26.

The maintainer accepts the core/application-adapter separation and the
two-application proof threshold in this RFC. Acceptance settles the
architectural direction. It does not mean a second adapter has been selected or
implemented, and it does not create an implementation-level portability claim.

WordPress work may continue independently. Platform-specific code is allowed
where it belongs: the WordPress mapping, adapter, examples, and tests. It must
not turn WordPress users, Abilities, hooks, options, transients, or MCP Adapter
contracts into mandatory core concepts.

## Problem

The specification is protocol-neutral and the executable reference kernel uses
injected application ports, but the only real application integration is
WordPress. Continued implementation can therefore create two opposite errors:

1. WordPress details may leak into the core until the project is portable only
   in name.
2. The project may claim broad portability from abstractions that have never
   been exercised by a second application authority model.

Multiple WordPress entry protocols do not resolve the second problem. MCP,
REST, CLI, and A2A can all reach the same WordPress users, permissions, and
Abilities. They test protocol adaptation, not application-platform portability.

Without an explicit decision, contributors cannot consistently decide whether
a new identity field, persistence mechanism, permission check, or capability
contract belongs in the core or a platform adapter.

## Goals

- Keep core records, lifecycle, policy, approval, budget, and evidence semantics
  independent of any application platform.
- Preserve each application's native authorization as mandatory and
  authoritative.
- Give WordPress implementation work a clear boundary without blocking it on a
  speculative second product.
- Define an observable threshold for claiming implementation portability.
- Make platform leakage detectable in review and conformance tests.

## Non-goals

- Select the second reference platform.
- Require another CMS.
- Define a universal application plugin API or capability registry.
- Require every implementation to support multiple platforms.
- Delay WordPress stages until a second adapter exists.
- Treat multiple protocols for one application as multiple platform proofs.

## Proposal

### 1. Layer model

```text
Protocol request
      |
      v
Protocol adapter
      |
      v
Governance Core
  canonical action / policy / delegation
  approval / budget / evidence
      |
      v
Application adapter
      |
      v
Native application authorization and capability
```

A package may contain both adapter roles, but the trust and authority
boundaries remain distinct.

### 2. Core ownership

The core may define:

- normalized execution context and canonical action;
- restrictive policy decisions;
- delegation bounds;
- exact approval and replay semantics;
- operational budgets;
- evidence semantics and privacy constraints;
- adapter contracts and conformance invariants.

The core must not require:

- a WordPress user, role, capability, Ability, hook, option, or transient;
- an MCP tool, session, transport, or authorization representation;
- a platform-specific database schema or lifecycle callback;
- application business logic.

Platform-specific identifiers may appear as typed values, such as
`application.type`, but the core must not infer platform authority from their
syntax.

### 3. Application-adapter ownership

An application adapter owns the mapping for:

- native application principals;
- native capabilities or operations;
- input validation and resource identity;
- application permission checks;
- execution through existing business logic;
- application-specific persistence and concurrency primitives;
- safe result and error mapping.

Governance may narrow a native allow. Neither the core nor an adapter may widen
native application authority.

### 4. Reference implementations

A reference adapter demonstrates how one platform satisfies the core boundary.
It does not redefine the boundary for other applications.

WordPress is the first reference adapter because it provides users, roles,
capabilities, Abilities, and an official MCP Adapter. These are useful native
primitives, not universal requirements.

The second adapter may be a small standalone application. It does not need to
be production-ready or use the same programming language, persistence layer,
or protocol as WordPress.

### 5. Portability claims

The specification may describe a platform-neutral design before two adapters
exist. The executable project must not describe its portability as demonstrated
until both application adapters pass the proof requirements below.

Adding REST, CLI, or A2A access to the WordPress adapter does not count as a
second application adapter. A materially distinct adapter must use a different
native principal, permission, and capability boundary.

## Data flow and trust boundaries

| Boundary | Adapter responsibility | Invariant |
|---|---|---|
| Protocol to governance | Authenticate protocol claims and normalize context | Protocol metadata cannot invent application authority |
| Governance to application adapter | Pass the exact governed capability and principal context | Core decisions cannot bypass native authorization |
| Application adapter to native permission | Resolve and check the effective principal at execution time | Every non-allow remains a denial |
| Application adapter to native execution | Invoke existing validated business logic | Adapter does not become a parallel capability implementation |
| Native result to evidence | Project only required decision and outcome fields | Raw input, output, and credentials do not become evidence |

## Security and privacy

The separation preserves the existing security invariants:

- application authorization remains mandatory;
- protocol, client, agent, and application-principal identities remain distinct;
- approval remains bound to the canonical action rather than a platform UI;
- permissions, policy, delegation, and mutable preconditions are re-checked
  before side effects;
- adapter failure or missing context fails closed;
- application-specific secrets and payloads do not enter generic evidence;
- platform storage and concurrency guarantees are documented by the mapping.

A falsely generic core creates security risk when it silently assumes one
platform's permission timing or identity semantics. A falsely broad portability
claim creates deployment risk by suggesting those assumptions have been tested
where they have not.

## Evidence

This RFC adds no event types or fields.

Each adapter must map core evidence requirements to its own operational store
without changing event semantics. Adapter-specific diagnostic metadata may be
stored separately, subject to the same secret minimization and retention rules.

## Compatibility and upstream relationship

The decision does not replace WordPress Abilities, the official WordPress MCP
Adapter, MCP, A2A, REST, or another application's native capability API. It
defines where those upstream contracts meet governance.

Existing canonical action and approval lifecycle fixtures remain unchanged.
Existing WordPress implementation code remains a reference adapter and requires
no data or API migration.

## Conformance and testing

A single application adapter may claim compatibility with its documented core
subset when it meets the ordinary conformance requirements. It must not claim
that the project has demonstrated platform portability.

An implementation-level portability claim requires at least two materially
distinct application adapters, including one non-WordPress adapter. Both must:

1. use the same canonical action and lifecycle semantics;
2. pass the same protocol-neutral validation, approval, replay, and evidence
   fixtures applicable to their supported capability classes;
3. prove that an application denial cannot be widened;
4. prove that governance can narrow an application allow;
5. re-check native permission before a side effect;
6. produce semantically equivalent minimized evidence;
7. document platform-specific assumptions and unsupported behavior.

The second adapter must not import or emulate WordPress permission APIs merely
to satisfy the test. Its native authority boundary must be independently
meaningful.

## Migration

No runtime or data migration is required.

Documentation and future code organization should use `core`,
`protocol adapter`, and `application adapter` consistently. Existing
WordPress-specific source notes must identify their narrower scope when they
could otherwise be mistaken for the project boundary.

## Alternatives

### Treat WordPress as the product boundary

Rejected because it contradicts the protocol-neutral specification and would
make the core unavailable to other web applications.

### Claim portability from dependency injection alone

Rejected because interface shape cannot reveal every hidden assumption about
identity, authorization timing, concurrency, or capability semantics.

### Count WordPress MCP and REST as two adapters

Rejected because they share the same application principal, authorization, and
capability boundary. They prove protocol portability only.

### Require a second full CMS implementation immediately

Rejected because it would expand scope before the first vertical path is
stable. A minimal non-WordPress reference application can provide the needed
counterexample at lower maintenance cost.

### Define a universal application capability API

Rejected because applications should retain their native business operations
and authorization. The governance project defines an adapter boundary, not a
replacement platform.

## Open questions

- Which non-WordPress application provides the smallest meaningful second
  authority and capability model?
- Which subset of the conformance fixtures should every read-only adapter be
  required to share before write and approval features are implemented?

These questions do not block the accepted boundary or proof rule.

## References

- [`../SPEC.md`](../SPEC.md)
- [`../mappings/WORDPRESS.md`](../mappings/WORDPRESS.md)
- [`../src/javascript/README.md`](../src/javascript/README.md)
- [`../OPEN-QUESTIONS.md`](../OPEN-QUESTIONS.md)
