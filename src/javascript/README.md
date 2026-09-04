# JavaScript Reference Kernel

**Status:** Executable reference structure, not a production implementation

This directory provides the first protocol-neutral implementation path through
the specification:

```text
canonical action
  -> proposal
  -> application permission and governance evaluation
  -> optional approval or strong authentication
  -> authoritative reconstruction
  -> execution-time re-evaluation
  -> single-use approval consumption
  -> application executor
  -> minimized evidence
```

## Modules

- `canonical-action-v1.mjs` implements the accepted RFC 0001 normalization,
  canonicalization, request hash contract, and default version 1 hasher factory.
- `reference-kernel.mjs` implements the lifecycle above with in-memory proposal,
  approval, evidence, and per-proposal locking.

The kernel requires explicit application ports for:

- application permission;
- delegation;
- policy evaluation;
- budget;
- execution preconditions;
- approver authorization;
- strong-auth verification;
- authoritative action reconstruction;
- application execution.

No permissive default is supplied for a security gate. A port exception or any
result other than literal `true` fails its boolean gate closed.
The `authentication` field on an approval is not trusted by itself; the strong
authentication port must independently verify it before the kernel records a
strong approval.

`authorizeApprover` verifies that the named approver has authority; it does not
verify that a human originated the decision. Likewise, `authorization_source`
is metadata rather than proof. The kernel has no independent human-decision
provenance port and does not implement accepted
[RFC 0003](../../rfcs/0003-human-authorization-assurance.md). A production
adapter MUST NOT expose `recordApproval` through an agent-controllable page or
same-session flow as though that alone were human approval.

## Run

From the repository root:

```sh
npm run example:reference
npm run test:reference
```

The executable example intentionally uses an in-memory approval and evidence
path. A production integration must replace that state with durable,
access-controlled storage and an application-appropriate atomic or idempotent
boundary. It must also supply adapter-specific authentication, request-forgery
protection, authorization, redaction, retention, and operational controls.

The reference kernel does not claim Draft 0.1 Core Conformance and is not a
WordPress plugin, MCP server, or agent runtime.
