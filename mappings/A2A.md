# A2A Protocol Mapping

**Mapping version:** Draft 0.1  
**Protocol baseline:** A2A 1.0  
**Status:** Future adapter design  
**Last reviewed:** 2026-08-25

## 1. Purpose

This document preserves a future adapter boundary between the Agentic Web
Governance core and A2A. A2A is not required for Draft 0.1 implementation.

## 2. Responsibility boundary

A2A defines agent discovery, Agent Cards, skills, messages, artifacts, task
lifecycle, supported protocol bindings, and authentication declarations.

The governance core defines whether and under what delegated authority an A2A
request may cause application actions or data transmission.

```text
A2A Agent Card / message / task
              |
              v
          A2A adapter
              |
              v
      Governance core
              |
              v
  Application capabilities
```

## 3. Concept mapping

| A2A concept | Core mapping | Notes |
|---|---|---|
| Agent Card | Discovery and claimed agent capabilities | Public card is not authorization |
| Security scheme | Adapter authentication requirements | Validate before using identity claims |
| Agent identity/provider metadata | Candidate `agent_identity` context | Keep issuer and trust status |
| Skill | One or more governed application capabilities | Do not assume one skill equals one capability |
| Message | Input to adapter/orchestration | Treat content as untrusted |
| Task | External long-running interaction | Map to internal work handle only when defined |
| Artifact | Data crossing an agent boundary | Apply data and transmission policy |
| Context/task ID | Correlation context | Not proof of authorization |

## 4. Agent Card handling

A public Agent Card may be discovered at
`/.well-known/agent-card.json` or through other A2A mechanisms.

Implementations must:

- treat card content as claims, not proof of authority;
- avoid publishing credentials or internal implementation details;
- use HTTPS and validate signatures when present and trusted;
- protect extended Agent Cards with the declared authentication controls;
- apply least disclosure to skills and metadata;
- record the card version or digest when it materially affects policy.

## 5. Skills and application capabilities

An A2A skill can represent orchestration over multiple application capabilities:

```text
A2A skill: publish reviewed campaign
        |
        +-> create or update draft
        +-> request approval
        +-> publish content
        +-> notify external service
```

Each underlying application action must retain its own permission, policy, data,
approval, and evidence semantics. Skill-level authorization must not flatten or
bypass those checks.

## 6. Tasks and long-running work

Before implementing A2A Tasks, define a protocol-neutral internal work model
covering:

- initiating principal, agent, and client;
- original proposal and request hash;
- policy, profile, and delegation versions;
- intermediate approvals;
- cancellation and revocation behavior;
- artifacts and external transmissions;
- terminal evidence.

A2A task continuation must not inherit authority indefinitely. Long-running work
must define revalidation points and expiry.

## 7. Artifacts and data policy

Receiving or sending an A2A artifact is a data-boundary event. Policy should
consider:

- source and recipient agent;
- artifact type and data classes;
- declared and actual purpose;
- storage and retention;
- downstream transmission;
- integrity and authenticity claims.

Artifact metadata and natural-language descriptions must not bypass schema or
content validation.

## 8. Approval

A2A interactions may require information or authorization during a task. The
adapter may use protocol-native interactions, but the core remains responsible
for:

- deciding when approval is required;
- authorizing the approver;
- binding approval to the exact action;
- re-checking permission and policy;
- recording evidence.

## 9. Adoption rule

Implement an A2A adapter only when a concrete use case requires agent-to-agent
task exchange that cannot be represented adequately by existing application or
MCP interfaces.

The existence of A2A 1.0 does not prove that A2A will be a universal web agent
protocol, and the governance core must remain usable without it.
