# Agentic Web Governance

**Status:** Draft 0.1, Request for Comments  
**Last reviewed:** 2026-09-04

Agentic Web Governance is an open specification for controlling actions that AI
agents perform on web systems on behalf of people and organizations.

The project focuses on the layer between agent protocols and application
authority:

```text
Human or organization authority
              |
              v
       Agent or client
              |
              v
      Protocol adapters
     (MCP, WebMCP, A2A, REST)
              |
              v
      Governance controls
  policy / delegation / approval
  data handling / budgets / evidence
              |
              v
      Application adapter
              |
              v
      Application capability
```

WordPress is the first reference platform. The specification is not limited to
WordPress and does not define a new agent communication protocol.

## Portability status

The core specification and reference kernel are platform-neutral. WordPress is
the first application adapter and proving ground, not the product boundary.
The project does not yet claim implementation-level portability: that claim
requires a second, non-WordPress application adapter to preserve the same core
semantics and pass the same protocol-neutral conformance fixtures. Exposing one
WordPress integration through multiple protocols does not satisfy that test.

See [RFC 0002](rfcs/0002-platform-adapter-boundary.md) for the accepted core and
adapter boundary and its portability proof rule.

## Project principles

- Preserve human and organizational authority.
- Apply least privilege to every delegated action.
- Keep governance independent of MCP, WebMCP, A2A, and future protocols.
- Reuse upstream identity, authorization, and agent primitives where available.
- Bind approvals to exact requests and re-check permissions at execution time.
- Do not confuse agent-driven UI activation with verified human authorization.
- Treat agent input and retrieved content as untrusted.
- Record useful evidence without turning audit logs into a second data leak.
- Separate implementable controls from research hypotheses.

## Documents

| Document | Purpose |
|---|---|
| [VISION.md](VISION.md) | Project purpose, values, and definition of success |
| [SPEC.md](SPEC.md) | Protocol-neutral core model and conformance requirements |
| [GOVERNANCE.md](GOVERNANCE.md) | Policy, delegation, approval, and operational control model |
| [THREAT-MODEL.md](THREAT-MODEL.md) | Assets, trust boundaries, attacker stories, and mitigations |
| [EVIDENCE.md](EVIDENCE.md) | Audit and evidence event model |
| [OPEN-QUESTIONS.md](OPEN-QUESTIONS.md) | Decisions intentionally left open |
| [REFERENCES.md](REFERENCES.md) | Standards, upstream projects, public guidance, and research |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution paths, RFC decisions, and maturity rules |
| [SECURITY.md](SECURITY.md) | Vulnerability reporting, security scope, and invariants |
| [LICENSE.md](LICENSE.md) | Documentation and source-code licensing policy |
| [schemas/action-v1.schema.json](schemas/action-v1.schema.json) | Machine-readable canonical action v1 contract |
| [fixtures/action-v1/README.md](fixtures/action-v1/README.md) | Cross-runtime canonicalization and digest fixtures |
| [fixtures/approval-lifecycle-v1/README.md](fixtures/approval-lifecycle-v1/README.md) | Fail-closed approval and execution lifecycle fixtures |
| [src/javascript/README.md](src/javascript/README.md) | Executable protocol-neutral reference kernel and implementation boundaries |
| [profiles/JAPAN.md](profiles/JAPAN.md) | Optional Japan governance profile |
| [mappings/WORDPRESS.md](mappings/WORDPRESS.md) | Mapping to WordPress Abilities and agent infrastructure |
| [mappings/MCP.md](mappings/MCP.md) | Mapping to Model Context Protocol |
| [mappings/WEBMCP.md](mappings/WEBMCP.md) | Mapping to the browser and agent-facing WebMCP API |
| [mappings/A2A.md](mappings/A2A.md) | Future mapping to the A2A protocol |
| [rfcs/README.md](rfcs/README.md) | RFC process and proposal lifecycle |
| [rfcs/0002-platform-adapter-boundary.md](rfcs/0002-platform-adapter-boundary.md) | Accepted platform-adapter boundary and portability proof rule |
| [rfcs/0003-human-authorization-assurance.md](rfcs/0003-human-authorization-assurance.md) | Accepted human-authorization provenance requirement |
| [examples/wordpress/README.md](examples/wordpress/README.md) | Experimental read-only WordPress reference plugin |

The documents linked above are the canonical project definition.

## Current scope

Draft 0.1 defines:

- a normalized execution context;
- risk and data-handling metadata;
- restrictive policy decisions;
- scoped delegation;
- exact-request approval;
- external-transmission controls;
- rate and iteration budgets;
- privacy-aware evidence records;
- adapter boundaries for WordPress, MCP, WebMCP, and A2A.

It includes reference conformance tooling for the accepted canonical action
representation, an executable JavaScript reference kernel, and an experimental
read-only WordPress Ability integration. It does not provide a production
governance implementation or claim that an application integration conforms to
the specification.

## Reference implementation path

The first executable path connects canonical action hashing, proposal-time
evaluation, approval, authoritative reconstruction, execution-time re-checks,
single-use approval consumption, application execution, and minimized evidence.
All security-relevant application decisions enter through explicit ports, so a
future WordPress or protocol adapter cannot gain authority from the kernel.

Run the deterministic in-memory example:

```sh
npm run example:reference
```

The protocol-neutral kernel is intentionally not durable or externally
reachable. The WordPress example adds an application adapter without changing
the kernel's authority boundaries. See
[`examples/wordpress/README.md`](examples/wordpress/README.md) for its narrower
runtime and storage limits.

The reference kernel verifies approver authority but does not yet verify that a
required human decision originated outside the requesting agent's automation
authority. It therefore does not implement accepted
[RFC 0003](rfcs/0003-human-authorization-assurance.md).

## Conformance tests

The RFC 0001 fixture suites run independent JavaScript and PHP paths for
canonical action validation and hashing, then exercise approval binding,
execution-time reconstruction, expiry, replay control, mutable re-checks, and
the executable reference path:

```sh
npm ci
composer install
npm test
```

Dependency versions are pinned in `package-lock.json` and `composer.lock`.

## Non-goals

This project is not:

- a replacement for the WordPress Abilities API or official MCP Adapter;
- a new MCP, WebMCP, or A2A implementation;
- a generic agent runtime or workflow engine;
- a universal agent identity, reputation, or marketplace standard;
- a remote shell, unrestricted database tool, or arbitrary code executor;
- a legal compliance certification system.

## Contributing

Start with [CONTRIBUTING.md](CONTRIBUTING.md) and
[OPEN-QUESTIONS.md](OPEN-QUESTIONS.md). Material changes to the core model
should be proposed through the [RFC process](rfcs/README.md). Report suspected
vulnerabilities through the private process in [SECURITY.md](SECURITY.md).

## Licensing

Documentation and specification material is licensed under
[CC BY 4.0](LICENSES/CC-BY-4.0.txt). Source code and executable examples are
licensed under [GPL-2.0-or-later](LICENSES/GPL-2.0-or-later.txt). Code snippets
embedded in documentation may be used under either license. See
[LICENSE.md](LICENSE.md) for the complete policy.
