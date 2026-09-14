# References and Evidence Map

**Last reviewed:** 2026-09-15

## 1. Evidence levels

This project labels claims as:

| Level | Meaning | Use |
|---|---|---|
| A | Implemented or production-grounded platform capability | May be a current implementation dependency |
| B | Published standard or active upstream design with evolving adoption | Preserve an adapter boundary |
| C | Research-backed architectural direction | Use as a hypothesis, not a protocol requirement |
| D | Speculative or long-term ecosystem idea | Research only or non-goal |

An evidence level is not a security rating or endorsement.

## 2. WordPress and upstream agent infrastructure

### WordPress Abilities API [A]

- [Abilities API handbook](https://developer.wordpress.org/apis/abilities-api/)
- [Abilities API hooks](https://developer.wordpress.org/apis/abilities-api/hooks/)
- [WordPress 7.1 execution lifecycle filters](https://make.wordpress.org/core/2026/07/29/new-execution-lifecycle-filters-for-the-abilities-api-in-wordpress-7-1/)
- [`WP_Ability::execute()` reference](https://developer.wordpress.org/reference/classes/wp_ability/execute/)
- [`wp_register_ability()` reference](https://developer.wordpress.org/reference/functions/wp_register_ability/)
- [Abilities REST API endpoints](https://developer.wordpress.org/apis/abilities-api/rest-api-endpoints/)
- [WP-CLI `wp ability`](https://developer.wordpress.org/cli/commands/ability/)

Verified project use: WordPress provides a central registry for machine-readable
capabilities with JSON Schema, execution callbacks, and permission callbacks.
The reference implementation requires WordPress 7.1 because it adds the
post-permission authorization filter used as the governance boundary. This is
the canonical WordPress application-capability layer.

### Official WordPress MCP Adapter [A]

- [WordPress MCP Adapter repository](https://github.com/WordPress/mcp-adapter)
- [MCP Adapter 0.6.1 release](https://github.com/WordPress/mcp-adapter/releases/tag/v0.6.1)
- [Default MCP server guide](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/guides/default-server.md)
- [Creating Abilities for MCP](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/guides/creating-abilities.md)
- [WordPress AI Building Blocks](https://make.wordpress.org/ai/2025/07/17/mcp-adapter/)

Verified project use: the official adapter bridges WordPress Abilities to MCP
tools, resources, and prompts and provides HTTP/STDIO transports, permissions,
multi-server support, and observability extension points. This project must not
ship a competing generic MCP server. Version 0.6.1 delegates Ability-backed
permission and execution to the target `WP_Ability`, so Core-level governance
hooks cover both direct Ability and MCP paths.

### Automattic Agents API [B]

- [Automattic Agents API repository](https://github.com/Automattic/agents-api)
- [Agents API 0.11.2 release](https://github.com/Automattic/agents-api/releases/tag/v0.11.2)

Verified project use: the package defines generic agent, execution-principal,
access-grant, policy, pending-action, memory, workflow, and external-client
contracts. Its pending-action boundary leaves concrete product storage, routes,
UI, permission ceilings, handlers, and terminal audit behavior to consumers.
Version 0.11.2 also includes idempotent recurring-routine registration,
generation fencing, authoritative reconciliation locking, and safe drain
diagnostics. These are useful operational substrate, but do not replace AWG's
application authorization, budgets, idempotency, outcome reconciliation, or
evidence requirements. Adoption should be feature-detected and adapter-based
while the project evolves.

### WordPress Agent User proposal [B]

- [WordPress/ai issue #923](https://github.com/WordPress/ai/issues/923)

Verified project use: an explicit, auditable agent-user identity is an open
proposal, not a stable WordPress dependency. The specification must not freeze a
proprietary permanent agent identity model.

## 3. Agent protocols

### Model Context Protocol [A/B]

- [MCP 2026-07-28 specification](https://modelcontextprotocol.io/specification/2026-07-28)
- [MCP 2026-07-28 release notes](https://blog.modelcontextprotocol.io/posts/2026-07-28/)

Verified project use: MCP provides tools, resources, prompts, discovery,
authorization, and optional extensions. The 2026-07-28 release uses a stateless
core, per-request capability information, header-based routing, Multi Round-Trip
Requests, authorization hardening, and an optional Tasks extension.

### WebMCP [B]

- [WebMCP Draft Community Group Report](https://webmachinelearning.github.io/webmcp/), 2026-09-14 snapshot
- [WebMCP repository](https://github.com/webmachinelearning/webmcp)
- [Browser and Agent Implementation Status](https://github.com/webmachinelearning/webmcp/blob/main/implementation-status.md)
- [Chrome WebMCP Imperative API](https://developer.chrome.com/docs/ai/webmcp/imperative-api)
- [Pull request 281: origin in observed tool collections](https://github.com/webmachinelearning/webmcp/pull/281)
- [Pull request 251: JavaScript object input for `executeTool()`](https://github.com/webmachinelearning/webmcp/pull/251)
- [Pull request 302: registration and lookup algorithm corrections](https://github.com/webmachinelearning/webmcp/pull/302)
- [Issue 255: tool collections and progressive disclosure](https://github.com/webmachinelearning/webmcp/issues/255)
- [Issue 306: closed duplicate of issue 255](https://github.com/webmachinelearning/webmcp/issues/306)
- [Issue 288: page-side approval and agent-controlled UI](https://github.com/webmachinelearning/webmcp/issues/288)
- [Issue 298: proposed page-enforced write boundaries](https://github.com/webmachinelearning/webmcp/issues/298)
- [Issue 300: unregistration and in-flight execution](https://github.com/webmachinelearning/webmcp/issues/300)
- [Issue 307: non-autosubmit declarative-tool lifecycle](https://github.com/webmachinelearning/webmcp/issues/307)
- [Issue 308: proposed preservation of tool outcomes](https://github.com/webmachinelearning/webmcp/issues/308)
- [Pull request 289: proposed schema validation](https://github.com/webmachinelearning/webmcp/pull/289)
- [Pull request 301: proposed `executeTool()` algorithm corrections](https://github.com/webmachinelearning/webmcp/pull/301)
- [Pull request 296: headless browsing scenarios explicitly in scope](https://github.com/webmachinelearning/webmcp/pull/296)
- [Pull request 217: `consequentialHint`](https://github.com/webmachinelearning/webmcp/pull/217)

Verified project use: the 2026-09-14 draft defines a browser API for exposing
tools to user agents and agents, includes `consequentialHint`, and records the
provider document's origin in observed tool collections. The annotation is a
classification hint, not permission or approval. Origin is provenance that
must remain bound to tool identity; it does not grant application authority.
The agent-facing observation format remains implementation-defined, so an
adapter must not invent a missing origin or attribute one origin's policy,
delegation, or approval to a same-named tool from another origin.

The upstream repository explicitly includes headless browsing scenarios for
client-side WebMCP tools, including transitions between human-in-the-loop and
headless experiences. AWG treats execution mode as context rather than proof of
human presence or a source of additional authority; the same governance
boundaries apply in both modes. Issue 288 provides one observed example
supporting the agent self-approval threat hypothesis; it does not establish
universal browser behavior. Issue 298 is an open proposal for person-owned write
scope, optimistic concurrency against unread human edits, and page-owned
cancellation. Its limited, author-reported evaluation is useful implementation
evidence for tool-mediated write guards, while the issue itself notes that
those guards do not cover an agent that bypasses the tool path by automating the
page. AWG therefore tracks the proposal as supporting evidence and does not
depend on it as adopted WebMCP behavior.

Issue 300 records one Chrome 152 observation where a tool's side effect
completed but `executeTool()` rejected after the tool unregistered itself.
Chrome's documentation states that Chrome 153 preserves in-flight executions
after unregistration. AWG treats this as implementation and interoperability
evidence, not as proof of universal browser behavior: tool registration
lifetime and execution cancellation remain distinct, and an ambiguous failure
after dispatch must be reconciled before a non-idempotent retry. Issue 307 is an
open lifecycle clarification: populating a non-autosubmit declarative form may
leave the call waiting for later submission. AWG treats that as a nonterminal
protocol condition, not execution success, failure, or human approval, and does
not depend on the proposed `awaiting_submission` label.

Merged pull request 251 changes `executeTool()` to receive a JavaScript object
directly and reject non-object input before dispatch. Chrome's September 11
documentation also deprecates JSON-stringified input arguments from Chrome 155.
A future AWG WebMCP adapter should therefore pass a serializable object and must
not pre-stringify its arguments. Pull request 302 corrected registration
validation ordering and malformed lookup steps without creating a new
governance authority or replacing application-side validation.

Issue 306 was closed as a duplicate of issue 255. The primary issue remains an
open proposal for tool collections and progressive disclosure; it supports
least-exposure discovery but cannot grant authority. Issue 308 is an open
proposal to preserve refusal, partial-result, and completed-result information
instead of flattening it to `UnknownError`; it does not yet define an adopted
result envelope. Pull requests 289 and 301 remain open, so the project does not
depend on their proposed validation, error, or execution-algorithm semantics.

### A2A Protocol [B]

- [A2A 1.0 specification](https://a2a-protocol.org/latest/specification/)
- [A2A agent discovery](https://a2a-protocol.org/latest/topics/agent-discovery/)

Verified project use: A2A defines Agent Cards, skills, messages, artifacts, task
lifecycle, security declarations, and discovery including
`/.well-known/agent-card.json`. This supports a future adapter, not a Draft 0.1
dependency or a claim of universal adoption.

## 4. Data representation and cryptography

### Canonical JSON and request digests [A/B]

- [RFC 8259: JSON](https://www.rfc-editor.org/rfc/rfc8259)
- [RFC 7493: I-JSON](https://www.rfc-editor.org/rfc/rfc7493)
- [RFC 8785: JSON Canonicalization Scheme](https://www.rfc-editor.org/rfc/rfc8785)
- [NIST FIPS 180-4: Secure Hash Standard](https://www.nist.gov/publications/secure-hash-standard-0)
- [JSON Schema Draft 2020-12](https://json-schema.org/draft/2020-12)
- [`canonicalize` for JavaScript](https://www.npmjs.com/package/canonicalize)
- [`truschery/kanon` for PHP](https://packagist.org/packages/truschery/kanon)
- [Ajv JSON Schema validator](https://ajv.js.org/)
- [Opis JSON Schema for PHP](https://opis.io/json-schema/)

Project use: RFC 0001 uses JCS as the deterministic serialization layer for
exact-action approval hashes and Draft 2020-12 for the machine-readable action
shape. JCS defines serialization, not the semantic projection of an action, and
its I-JSON number and Unicode constraints require an additional project
profile. The listed libraries form the draft cross-runtime conformance paths;
lockfiles, strict parsers, and project fixtures remain authoritative for the
tested dependency versions.

## 5. Japan profile sources

These sources inform technical control mappings. They do not turn the profile
into legal advice or certification.

### AI safety

- [Japan AISI: Guide to Evaluation Perspectives on AI Safety, Version 1.20](https://aisi.go.jp/output/output_information/260707/), published 2026-07-07

Verified project use: the update explicitly addresses AI agent systems and adds
an observation-and-control perspective for autonomous behavior and interaction
with external environments.

### Business guidance

- [METI: AI Guidelines for Business, Version 1.2](https://www.meti.go.jp/shingikai/mono_info_service/ai_shakai_jisso/20260331_report.html), published 2026

Project use: track governance concerns and source versions. A detailed control
mapping requires subject-matter review before being presented as authoritative.

### Personal information

- [Personal Information Protection Commission: Alert on the Use of Generative AI Services](https://www.ppc.go.jp/news/careful_information/230602_AI_utilize_alert/)

Project use: motivate explicit classification, minimization, recipient policy,
and evidence for personal information transmitted to external AI services.

### AI contracts

- [METI: Checklist for Contracts on the Use and Development of AI](https://www.meti.go.jp/press/2024/02/20250218003/20250218003.html), published 2025-02-18
- [Checklist PDF](https://www.meti.go.jp/policy/mono_info_service/connected_industries/sharing_and_utilization/20250218003-ar.pdf)

Project use: motivate organization metadata for input/output use, third-party
provision, retention, rights, and review ownership. Technical metadata does not
replace contract review.

## 6. Security research [C]

- [AgentDojo: A Dynamic Environment to Evaluate Prompt Injection Attacks and Defenses for LLM Agents](https://arxiv.org/abs/2406.13352)
- [WASP: Benchmarking Web Agent Security Against Prompt Injection Attacks](https://arxiv.org/abs/2504.18575)

Project use: authenticated, tool-using agents can still be influenced by
untrusted content. Prompt injection is therefore addressed through authority
boundaries, schema validation, restrictive policy, approval, and evidence rather
than a claim of complete prevention.

## 7. Agentic Web research [C]

- [Agentic Web: Weaving the Next Web with AI Agents](https://arxiv.org/abs/2507.21206)
- [Build the Web for Agents, Not Agents for the Web](https://arxiv.org/abs/2506.10953)
- [From Semantic Web and MAS to Agentic AI: A Unified Narrative of the Web of Agents](https://arxiv.org/abs/2507.10644)
- [A Survey of Agent Interoperability Protocols](https://arxiv.org/abs/2505.02279)

Project use: these works support capability-oriented agent interfaces and the
need for governance, identity, security, and interoperability research. They do
not establish a single future web architecture.

## 8. Monitoring list

Before a release, review:

- WordPress Core Abilities changes;
- WordPress MCP Adapter releases and integration hooks;
- Automattic Agents API releases and contract stability;
- WordPress Agent User discussion;
- MCP specification and extension changes;
- WebMCP specification, implementation status, and approval-boundary work;
- A2A specification changes;
- Japan profile source versions;
- relevant prompt-injection and agent-security research.

Any source-dependent normative statement should retain a version or review date.
