# Vision

**Status:** Draft 0.1  
**Last reviewed:** 2026-08-25

## The change

The web is gaining a second operational audience. Alongside people who read and
operate human interfaces, AI agents increasingly discover capabilities, invoke
tools, exchange data, and initiate changes.

That shift changes the central question from:

> Can an agent call this function?

to:

> Under whose authority may this agent perform this exact action, on which
> data, under what constraints, with what human control, and with what evidence?

Authentication and application permissions answer only part of that question.
An authenticated agent can still be over-privileged, confused by untrusted
content, act outside the user's intent, transmit sensitive data, or repeat a
consequential operation.

## Project thesis

Agent-facing capability layers need a protocol-neutral governance boundary that
can further restrict application authority without expanding it.

```text
Application permission
        AND
Delegated authority
        AND
Organization policy
        AND
Required approval
        AND
Operational limits
        =
Eligible for execution
```

The application remains the final authority for its own resources. Governance
adds narrower constraints, review, and evidence. It must never turn a denied
application action into an allowed one.

## Why an open specification

Agent protocols and platform primitives are moving quickly. WordPress already
has an Abilities API and an official MCP Adapter. Automattic's Agents API is
developing reusable agent, authorization, policy, and pending-action contracts.
A2A defines a separate agent-to-agent layer.

Rebuilding those foundations would create duplication and lock the project to
unstable details. An open specification can instead:

- define the missing governance concepts;
- map them to existing platforms and protocols;
- expose unresolved questions early;
- support small reference implementations;
- let useful ideas move upstream without making product ownership the goal.

## WordPress as the first reference platform

WordPress is a useful proving ground because it combines:

- a large and diverse installed base;
- content, user, commerce, membership, and custom application data;
- a mature role and capability system;
- machine-readable Abilities with schemas and permission callbacks;
- an official MCP integration;
- an active upstream agent infrastructure effort.

The reference path is intentionally narrow:

```text
Agent proposes an action
        -> policy evaluates it
        -> human approval is requested when required
        -> WordPress permission is re-checked
        -> the Ability executes
        -> evidence is recorded
```

## Design values

### Human authority

Agents act under bounded authority. Consequential actions remain reviewable,
revocable where possible, and attributable to both the acting software and the
human or organization that authorized it.

### Interoperability

Governance concepts belong in the core. Protocol-specific mechanics belong in
adapters. The same policy should apply whether an action arrives through MCP,
REST, A2A, a CLI, or a future interface.

### Evidence over assurances

The project should make decisions and actions reconstructable. It should not
claim that vague safety labels, model explanations, or authentication alone
prove that an action was appropriate.

### Data restraint

Only the data required for an action should cross a boundary. Evidence records
should be useful while minimizing secrets and personal data.

### Honest certainty

Implemented platform features, emerging standards, research-backed directions,
and speculation must be labeled differently. Adoption forecasts are not
requirements.

## Definition of success

The project succeeds if it provides a clear, testable vocabulary for governing
delegated web actions and helps implementations preserve these invariants:

1. No protocol bypasses application authorization.
2. Governance can restrict authority but cannot create new authority.
3. Approval is bound to the exact operation that is executed.
4. External data transmission is explicit and policy-controlled.
5. Unknown identities remain unknown rather than being guessed.
6. Consequential actions produce privacy-aware, integrity-protected evidence.
7. Upstream standards can evolve without requiring the governance model to be
   rewritten.

Success does not require a standalone commercial plugin. Adoption of the
concepts by WordPress Core, upstream agent projects, protocol communities, or
other implementations is also a successful outcome.
