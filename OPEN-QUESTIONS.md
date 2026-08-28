# Open Questions

**Last reviewed:** 2026-08-26

These questions are intentionally unresolved. They should become RFCs when a
proposal changes the normative model.

## Core model

### Q-004: Policy representation

Should the specification define a portable policy language, a JSON interchange
model, or only behavioral interfaces? A portable format could aid testing but
could accidentally become a weak new authorization language.

### Q-005: Identity semantics

What minimum claims distinguish an application principal, OAuth client, agent,
runtime instance, and delegated authority? How should a future WordPress Agent
User map without freezing a proprietary identity schema now?

### Q-006: Policy changes and pending approvals

Which changes invalidate existing approvals? The current safe default is to
invalidate when authorization, impact, or required controls change, but a
portable rule is not yet defined.

### Q-007: Evidence integrity level

What evidence guarantees are required for base conformance versus a stronger
profile? Options range from access-controlled append-oriented storage to signed
exports or externally anchored event chains.

## WordPress reference implementation

### Q-009: Agents API dependency boundary

Which Agents API contracts are stable enough to consume directly, and which
need local adapters? The project should reuse pending-action and execution
principal contracts without making an experimental package a hard runtime
dependency too early.

### Q-010: Storage materialization

When should policy versions, delegations, approvals, evidence, and rate counters
move from WordPress-native options/cache to custom tables? Schema should follow
real query and retention requirements.

### Q-011: Multisite ownership

Are policies, identities, delegations, and evidence scoped per site, per network,
or both? The answer changes authority and data-isolation boundaries.

### Q-012: Public read-only Abilities

Can any governed Ability execute without an authenticated application
principal? Read-only can still disclose sensitive data or consume substantial
resources, so public exposure requires a separate model.

## Approval and data handling

### Q-013: Reviewable change format

What common representation can show consequential changes across content,
settings, users, commerce, and external transmissions without relying on an LLM
summary?

### Q-014: Data classification ownership

Who classifies capability inputs, outputs, and resource fields: the registering
plugin, site administrator, governance profile, or a combination? How are
conflicts and stale classifications detected?

### Q-015: Provider registry semantics

Which provider/model/retention/training-use fields are facts, contractual notes,
or administrator assertions? The UI and evidence model must not imply that
unchecked metadata is independently verified.

### Q-016: Revocation during execution

How should long-running work react when delegation, policy, or approval is
revoked after execution starts? Cancellation, compensation, and evidence differ
by application and protocol.

## Profiles and research

### Q-017: Japan profile review

Which legal, privacy, procurement, and AI-safety experts should review the Japan
profile before it is described as useful for organizational governance? The
profile must remain technical guidance, not legal advice.

### Q-018: Conformance testing

Should the project publish protocol-neutral fixtures and adversarial test cases
for permission widening, prompt injection, approval replay, external
transmission, and evidence redaction?

RFC 0001 now includes protocol-neutral canonical-action and approval-lifecycle
fixtures, including replay control and mutable authorization re-checks.
External transmission, prompt-injection, evidence-redaction, and broader
authorization fixtures remain open.

### Q-019: A2A and asynchronous work

Which real WordPress use case justifies an A2A adapter or durable task model?
The project should not build either solely because a protocol supports it.

### Q-020: Broader platform mappings

Which second reference platform would test whether the core model is genuinely
portable rather than WordPress terminology with generic names?

[RFC 0002](rfcs/0002-platform-adapter-boundary.md) fixes the proof threshold:
the second adapter must be non-WordPress and pass the same protocol-neutral
conformance fixtures. The platform choice remains open.

## Resolved implementation questions

### Q-008: Supported interception point

**Resolved 2026-08-26.** The reference requires WordPress 7.1+ and uses
`wp_ability_permission_result`. WordPress 6.9 and 7.0 are intentionally outside
the initial compatibility boundary because they would require a second,
registration-time wrapper path. The selected filter covers official MCP Adapter
0.6.1 execution because it delegates to the target `WP_Ability`. See
[`mappings/WORDPRESS.md`](mappings/WORDPRESS.md#41-supported-interception-points).
