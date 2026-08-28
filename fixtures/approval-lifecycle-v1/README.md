# Approval Lifecycle v1 Fixtures

These protocol-neutral fixtures exercise the fail-closed approval and execution
lifecycle required by [RFC 0001](../../rfcs/0001-canonical-action-representation.md).
They reuse canonical actions from the `action-v1` fixture suite.

The fixture state has three parts:

- an immutable proposal and its request hash;
- an approval decision, approver, expiry, and single-use replay state;
- execution-time authoritative reconstruction and mutable checks.

An attempt records an `executed` result only after the approved action hash is
reconstructed and every current check passes. The simulator increments
`side_effect_count` only at that boundary.

## Decision order

The reference evaluator uses this deterministic fail-closed order:

1. approval and proposal binding;
2. approval decision;
3. expiry, where `now >= expires_at` is expired;
4. single-use replay state;
5. authoritative action reconstruction;
6. approved and reconstructed hash equality;
7. application permission;
8. delegation;
9. policy;
10. budget;
11. preconditions;
12. approval consumption and execution.

Production implementations may organize internal work differently, but they
must not permit a side effect unless every gate succeeds. Single-use checking,
consumption, and side-effect dispatch require an application-appropriate atomic
or idempotent boundary; this sequential simulator does not define storage or a
distributed transaction protocol.

## Run

From the repository root:

```sh
npm run test:lifecycle
```

The JavaScript and PHP runners independently evaluate every attempt and compare
their complete outcomes. These files are conformance test notation, not a
portable approval storage schema or a production authorization implementation.
