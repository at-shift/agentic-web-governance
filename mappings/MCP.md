# Model Context Protocol Mapping

**Mapping version:** Draft 0.1  
**Protocol baseline:** MCP 2026-07-28  
**Last reviewed:** 2026-08-25

## 1. Purpose

This document maps MCP concepts to the protocol-neutral Agentic Web Governance
core. It does not define or implement MCP.

For WordPress, the official WordPress MCP Adapter owns Ability-to-MCP exposure
and transport behavior. A reference implementation should integrate with that
project rather than ship a parallel MCP server.

## 2. Responsibility boundary

| Concern | MCP / MCP implementation | Governance core |
|---|---|---|
| Protocol messages and versioning | Owns | Does not redefine |
| Tools, resources, prompts | Owns protocol representation | Evaluates governed application actions |
| Transport routing | Owns | Consumes validated context |
| Client authentication claims | Validates according to MCP authorization | Uses claims without conflating identities |
| User consent interaction | Provides protocol mechanisms | Determines policy and approval requirements |
| Tool execution | Invokes implementation handler | Requires policy and application authorization |
| Tasks extension | Provides asynchronous protocol model | Maps only when a concrete internal work model exists |
| Audit and evidence | May provide observability | Owns governance decision and execution evidence |

## 3. Request mapping

An MCP tool invocation maps as follows:

| MCP value | Core value | Notes |
|---|---|---|
| Server and endpoint | `application` / adapter context | Not an authenticated user by itself |
| Client identity metadata | `client_identity` | Retain issuer and validation status |
| Tool name | `capability` mapping | Mapping must be deterministic and authorized |
| Tool arguments | `canonical_arguments` | Validate against application schema |
| Request metadata | protocol/trace/authorization context | Exclude non-authoritative metadata from request hash |
| Result or error | execution outcome | Sanitize before evidence |

MCP client identity is not automatically agent identity. MCP authorization is
not automatically delegation to a specific application action.

## 4. Discovery

Use MCP's own discovery and tool/resource/prompt mechanisms. Do not introduce a
parallel universal discovery document.

For WordPress, Ability exposure remains opt-in through the official MCP Adapter.
Governance may further hide or deny an exposed Ability, but must not imply that
all discoverable tools are safe for every user or context.

Tool descriptions and annotations are untrusted inputs unless they come from a
trusted, validated server and application source. They are not substitutes for
authorization policy.

## 5. Authorization and consent

The MCP 2026-07-28 specification includes authorization hardening and emphasizes
user consent and control. Governance implementations should:

- use standards-compatible MCP authorization mechanisms;
- validate issuer and client claims through the MCP layer;
- keep application principal, client, and agent identities distinct;
- require application authorization for the mapped capability;
- separately evaluate delegated authority and external transmission;
- avoid becoming a general-purpose authorization server unless explicitly
  designed and reviewed as one.

Protocol-level user interaction is a way to collect input or confirmation. It
does not decide which actions require approval or how approvals are persisted,
authorized, expired, or evidenced.

## 6. Multi Round-Trip Requests

MCP Multi Round-Trip Requests can carry additional input or confirmation over a
stateless request flow.

When used for governance approval:

1. The initial tool call becomes an immutable proposal.
2. The server returns an input-required response through the MCP mechanism.
3. The client presents a structured review to the user.
4. The response is bound to the original canonical action.
5. Application permission and governance are re-checked on retry.
6. Execution and evidence remain implementation responsibilities.

The client must not be trusted to preserve the proposal unchanged without
server-side request binding.

## 7. Statelessness and replay

The 2026-07-28 protocol core is stateless at the transport level. Governance
state such as pending actions, approvals, delegations, evidence, and budgets may
still be durable application state.

Every request must carry or resolve the context needed for policy. Implementers
must add application-level replay controls for non-idempotent actions and must
not rely on a transport session as the authorization boundary.

## 8. Header-based routing

MCP method and name headers can support routing, metering, and early policy.
They must be validated against the parsed request and mapped application
capability. A gateway decision based only on attacker-controlled headers is not
sufficient authorization.

## 9. Tasks extension

MCP Tasks is an optional extension for long-running work. The governance core
should map Tasks only after an implementation has a concrete internal work
handle and lifecycle.

Protocol task IDs must not become the canonical governance identity without a
mapping that preserves:

- initiating request and authority;
- policy and delegation version;
- approval state;
- cancellation and expiry semantics;
- evidence correlation.

## 10. Error mapping

Policy and application errors should map to stable MCP-safe responses without
exposing stack traces, secrets, private capability metadata, or rule internals.

Useful semantic categories include:

```text
authentication required
application permission denied
governance denied
approval required
strong authentication required
delegation expired or out of scope
rate or budget exceeded
invalid input
capability unavailable
execution failed
```

## 11. Conformance notes

An MCP adapter mapping is compatible with Draft 0.1 only if every governed tool
execution follows the same core policy, approval, and evidence path as other
adapters and application authorization remains mandatory.

MCP resources or prompts that only return data may still cross sensitive data
boundaries. Implementations must document whether and how they are governed.
