# OWASP AISVS 1.01 Test Evidence Map

**Status:** Review aid; no AISVS conformance claim  
**Last reviewed:** 2026-11-24  
**Initial AWG code/test snapshot:** `494f2edc841ed920299c1bb5694f3c14e9a51e49`  
**Additive review:** 2026-11-24, patch-baseline/input E2E changes based on `177fcbc`  
**AISVS source:** tag `v1.01`, commit `3ae3af45c363467b8ad823ef79219236e67ca22f`

## 1. Scope and interpretation

This review maps all entries in three published OWASP AISVS 1.01 chapters to
the existing AWG reference paths: C9 (37 active entries), C10 (39 active entries
and one removed identifier), and C12 (21 active entries). There are 98 rows,
not 98 passing controls. Other AISVS chapters, appendices, and underlying ASVS
requirements have not been assessed here. This is not a Level 1, 2, or 3 audit.

The authoritative requirement text and levels belong to OWASP:

- [C9: orchestration and agentic security](https://github.com/OWASP/AISVS/blob/v1.01/1.01/en/0x10-C09-Orchestration-and-Agentic-Action.md)
- [C10: MCP security](https://github.com/OWASP/AISVS/blob/v1.01/1.01/en/0x10-C10-MCP-Security.md)
- [C12: monitoring and logging](https://github.com/OWASP/AISVS/blob/v1.01/1.01/en/0x10-C12-Monitoring-and-Logging.md)
- [AISVS 1.01 use and ASVS relationship](https://github.com/OWASP/AISVS/blob/v1.01/1.01/en/0x03-Using-AISVS.md)

OWASP publishes AISVS under CC BY-SA 4.0. This map uses versioned identifiers
and short topic labels for navigation; consult the source for the complete
requirement. `v1.01-C9.2.1`, for example, identifies this exact edition. The
level column records the source level, not an assurance level attained by AWG.

The JavaScript kernel is in-memory and uses application ports. Its approval
tests use a fixture verifier, not a production human-authorization mechanism.
The WordPress implementation is one read-only Ability, not a deployed write
approval system. The WebMCP tests use a fake host. An upstream capability or a
written AWG requirement is not executable proof for the complete deployment.

### Evidence states

| State | Meaning |
|---|---|
| Partial | Identified assertions establish a bounded part of the requirement; the remaining proof is stated in the row. |
| Gap | Relevant behavior lacks an identified executable proof in the assessed reference paths. Code inspection alone is not a passing test. |
| Upstream | The main mechanism belongs to WordPress, MCP, the browser, or deployment infrastructure. Integration verification remains outstanding. |
| Not present | The assessed paths do not implement the relevant role or subsystem. Reassess if it is introduced; this is not an automatic N/A for an adopting application. |
| Removed | The source explicitly removed this identifier. It earns no coverage credit. |

No row is a full AISVS pass. These states are not a percentage score. In
particular, missing evidence is not presumed success and an expected-failure
CI baseline does not establish a control. Existing local E2E `ACCEPTED` results
retain their exact historical stack and scenario scope; this review neither
upgrades them nor makes AISVS a new condition for removing AWG's draft status.

## 2. Evidence catalogue

Keys below identify concrete test assertions or inspected harnesses. Test names
are stable lookup text in the linked files; source lines can move after this
snapshot. All unit/fixture assertions must be rerun when their code changes.

| Key | Source and lookup | Established slice and limit |
|---|---|---|
| K-PERM | [Kernel tests](../tests/reference-kernel-javascript.mjs): `application denial cannot be widened by policy` | A false application gate prevents the reference side effect despite policy allow. |
| K-GATES | Same file: `every explicit mutable gate fails closed` | Delegation/budget false, invalid policy, and failed preconditions deny. Ports are test doubles, not real quota or delegation services. |
| K-HUMAN | Same file: `missing approval remains pending without reconstruction or side effects`; `an unauthorized reviewer cannot create an approval`; `authorization source metadata without independent evidence fails closed`; `human authorization verifier denial, malformed result, and failure fail closed` | Missing or rejected assurance cannot authorize this kernel. The verifier itself is a fixture. |
| K-CONTEXT | Same file: `human authorization evidence is bound to the exact decision context` | Changed hash, reviewer, decision, method, timestamps, or single-use setting fail fixture verification. No production credential or key isolation proof. |
| K-BIND | Same file: `changed action cannot reuse an exact-request approval`; [action fixtures](../fixtures/action-v1/manifest.json): `principal-change`, `actor-change`, `capability-change`, `argument-change`, `resource-change`, `transmission-change`, `impact-change`, `delegation-change`, `policy-change` | Canonical projection/digest changes and reconstructed argument mismatch are detected. A digest is not a signature or proof of human intent. |
| K-RECHECK | [Kernel tests](../tests/reference-kernel-javascript.mjs): `execution-time permission revocation fails closed`; `a stronger execution-time policy requires a new approval` | Current gate/policy changes stop dispatch. No long-lived remote identity lifecycle is exercised. |
| K-EXPIRY | Same file: `an expired approval can be replaced without weakening the proposal`; [lifecycle fixture](../fixtures/approval-lifecycle-v1/manifest.json): `expiry-boundary` | Expired approvals cannot execute; a fresh verified approval is distinct from retrying the expired one. |
| K-REPLAY | [Kernel tests](../tests/reference-kernel-javascript.mjs): `approved proposal executes once and records sanitized evidence`; `executor failure consumes a single-use approval before retry`; `concurrent execution cannot spend one approval twice` | One in-memory approval cannot be spent twice in one kernel instance. The failure fixture throws before its side-effect counter increments. |
| K-EVIDENCE | Same file: `approved proposal executes once and records sanitized evidence`; `a verified human rejection is recorded without retaining raw evidence` | Event order and omission of raw action/arguments and fixture proof are asserted. No durable audit store or inference telemetry is exercised. |
| F-LIFECYCLE | [Lifecycle fixtures](../fixtures/approval-lifecycle-v1/manifest.json) and [runners](../fixtures/approval-lifecycle-v1/README.md): `delegation-invalid`, `budget-exhausted`, `precondition-failed`, `approval-binding-mismatch`, `replay-blocked` | JS/PHP sequential simulation compares fail-closed outcomes; no cryptographic grant verification or distributed transaction. |
| W-GATE | [WordPress tests](../tests/wordpress-ability-governance-php.php): `application false is preserved and policy is skipped`; `application WP_Error is preserved by identity`; `governance can narrow an application allow`; `policy exceptions fail closed`; `malformed policy decisions fail closed` | Governance cannot widen native denial; malformed/failed policy denies. WordPress functions are test doubles in this suite. |
| W-RATE | Same file: `rate limiter reserves up to the configured limit`; `rate limiter storage write failures deny`; `malformed rate state denies`; `MCP-style duplicate checks reserve one policy decision` | Request-count limiting and memoized probe/execute accounting. Not CPU, memory, recursion, token-cost, or wall-time enforcement. |
| W-CONTEXT | Same file: `in-request allows are isolated by execution context` | Changing client context causes a fresh decision. No real multi-tenant resource write is tested. |
| W-EVIDENCE | Same file: `successful execution emits minimized lifecycle evidence`; `evidence store is bounded and projects fields`; `an allow is denied when decision evidence cannot be stored` | Minimized, bounded evidence and fail-closed decision persistence. The [option store](../examples/wordpress/plugin/includes/class-option-evidence-store.php) is not immutable or independently protected from the site administrator. |
| W-EXPOSURE | Same file: `reference plugin exposes one read-only MCP Ability`; [plugin](../examples/wordpress/plugin/agentic-web-governance-reference.php) | Checks exposure/permission metadata; code declares an output schema. Metadata assertions do not execute native output validation. |
| B-OUTCOMES | [WebMCP tests](../tests/webmcp-adapter-javascript.mjs): the `proposalStatus` / `executionStatus` loop, `outputSchema`, and `new Ajv2020` | Four AWG envelopes are checked by Ajv in the test, and the host schema is copied. The helper does not install a runtime output validator. |
| B-GATES | Same file: blocks using `POLICY_DENIED`, `WRITE_REQUIRES_REVIEW`, and `INPUT_REJECTED_BY_APPLICATION` | Denied/pending policy and rejected input do not dispatch the fake kernel. Not a real browser security evaluation. |
| B-CANCEL | Same file: `Cancelled during policy evaluation`, `proposal-dispatched`, and pre-aborted-signal blocks | Cancellation before dispatch stops it; a completed result after dispatch is not falsely converted to rollback. No inference/process kill is implemented. |
| H-WP | [Local E2E verifier](../examples/wordpress/e2e/verify.php) and [acceptance boundary](../examples/wordpress/e2e/README.md) | Harness covers authenticated/anonymous discovery, hidden/missing Ability refusal, native permission denial, ten calls then rate denial, bounded evidence, and oversized initialization. It uses loopback HTTP and Application Passwords, not OAuth. Rerun November 24 on WP 7.1.5 / Adapter 0.7.2: synthetic invalid input stops before callbacks; Core/MCP normalized input agrees with one policy reservation; headerless current revision is refused. No prompt/OAuth or native-browser coverage. |
| H-SUPPLY | [E2E setup](../examples/wordpress/e2e/setup.sh) | Inspected archive-digest and exact-version checks. November 24 installation and official Core checksum verification passed. Not a malicious-archive or signer-verification test. |

## 3. C9 mapping

| Versioned ID | Level | Topic | State | Evidence and remaining proof |
|---|---|---|---|---|
| v1.01-C9.1.1 | 1 | Tool resource fencing | Partial | W-RATE; invocation counts only. No CPU/memory/egress or runtime timeout proof. |
| v1.01-C9.1.2 | 1 | Run spending limits | Partial | K-GATES, F-LIFECYCLE; fake budget denial, no actual recursion/token/cost meter. |
| v1.01-C9.1.3 | 2 | Fleet stop | Not present | No swarm runtime or all-instance stop controller. |
| v1.01-C9.2.1 | 1 | Human decision gate | Partial | K-HUMAN, B-GATES; real human-origin verification and privileged-write E2E are absent. |
| v1.01-C9.2.2 | 2 | Faithful review view | Gap | K-BIND proves digest behavior, not complete safe rendering. No production review UI. |
| v1.01-C9.2.3 | 2 | Trusted impact label | Partial | K-BIND detects an impact change; no trusted reversibility classifier is tested. |
| v1.01-C9.2.4 | 2 | Impact-based restriction | Partial | K-GATES exercises a supplied policy; no classifier-to-enforcement matrix. |
| v1.01-C9.2.5 | 2 | Runtime mutation boundary | Not present | No agent self-modification runtime. Protection of policy/admin stores needs deployment proof. |
| v1.01-C9.2.6 | 2 | Additional AI review | Not present | No AI review subsystem; deterministic gates do not supply that extra review. |
| v1.01-C9.2.7 | 2 | Review attack resistance | Not present | No AI review subsystem or adversarial review evaluation. |
| v1.01-C9.2.8 | 3 | Authenticated action binding | Partial | K-CONTEXT, K-BIND, K-REPLAY; no signed approval/nonce verifier or cross-worker durable consumption. |
| v1.01-C9.2.9 | 3 | Approval credential isolation | Gap | K-HUMAN rejects fixture evidence; it does not demonstrate isolated real signing credentials. |
| v1.01-C9.2.10 | 3 | Chain-wide impact gate | Not present | No independently established multi-step chain classifier. |
| v1.01-C9.2.11 | 3 | Chain expansion consent | Not present | No cumulative chain or expansion-approval implementation. |
| v1.01-C9.3.1 | 1 | Executor isolation | Upstream | Application ports and localhost binding are not sandbox/least-privilege deployment proof. |
| v1.01-C9.3.2 | 1 | Result shape enforcement | Partial | B-OUTCOMES checks fixtures; W-EXPOSURE declares a schema. No adversarial runtime result-validation test. |
| v1.01-C9.3.3 | 2 | Tool control declarations | Partial | W-EXPOSURE declares one read tool/schema; no complete privilege/resource-limit manifest. |
| v1.01-C9.3.4 | 2 | Declared control execution | Partial | W-GATE, W-RATE, B-GATES; no enforcement of all manifest constraints. |
| v1.01-C9.3.5 | 2 | Untrusted processing boundary | Not present | No model-side data processor or capability-isolation harness. |
| v1.01-C9.3.6 | 2 | Result-processing separation | Not present | No consuming agent runtime to evaluate. |
| v1.01-C9.3.7 | 2 | External component allowlist | Not present | No model-directed installer or external-tool resolver. |
| v1.01-C9.3.8 | 3 | Violation containment | Partial | W-GATE, B-GATES stop a call; no automatic ongoing-tool isolation or credential revocation. |
| v1.01-C9.3.9 | 2 | Writable-state tenancy | Gap | W-CONTEXT is only allow-cache isolation. No adversarial run/task/tenant storage boundary test. |
| v1.01-C9.4.1 | 2 | Agent cryptographic principal | Not present | Application identity is supplied; no agent credential issuer or downstream authentication. |
| v1.01-C9.4.2 | 2 | Signed execution chain | Not present | Request hashes are not an authenticated, non-repudiable chain of actions. |
| v1.01-C9.4.3 | 3 | Agent credential rotation | Not present | No managed agent credentials or rotation service. |
| v1.01-C9.4.4 | 3 | Persisted-state authenticity | Gap | Kernel state is in-memory; WordPress options have no tested integrity-protected persistent agent-state contract. |
| v1.01-C9.5.1 | 2 | Tool/argument policy | Partial | K-PERM, K-GATES, W-GATE, B-GATES; real parameter policies and adversarial object-level cases remain. |
| v1.01-C9.5.2 | 2 | Downstream delegation token | Not present | An action's delegation reference and a WordPress principal are not a protected downstream token. |
| v1.01-C9.5.3 | 2 | Non-model access enforcement | Partial | K-PERM, W-GATE, reference read policy; assess all entry points and application ports in a concrete deployment. |
| v1.01-C9.5.4 | 2 | Model credential exclusion | Partial | K-EVIDENCE, W-EVIDENCE omit raw proof/payload from evidence. No actual model-context or credential-flow inspection. |
| v1.01-C9.5.5 | 2 | Peer delegation policy | Not present | No inter-agent task delegation or A2A adapter. |
| v1.01-C9.5.6 | 3 | Current authority per action | Partial | K-RECHECK; real backend revocation across long-lived sessions/workers is untested. |
| v1.01-C9.5.7 | 2 | Delegation authority ceiling | Partial | K-PERM and invalid-delegation fixtures; no real grant derivation/attenuation implementation. |
| v1.01-C9.6.1 | 1 | Inference stop | Not present | B-CANCEL concerns tool dispatch only, not stopping model inference/output. |
| v1.01-C9.6.2 | 2 | Pending decision expiry | Partial | K-EXPIRY, K-HUMAN; no production approval-wait deadline controller. |
| v1.01-C9.6.3 | 3 | Independent stop channel | Not present | No out-of-band runtime shutdown channel. |

## 4. C10 mapping

WebMCP is not MCP: B-OUTCOMES is not evidence for MCP wire validation. WordPress
Application Password authentication also does not prove any OAuth token rule.
`Upstream` below requires inspection plus negative integration tests of the
actual installed adapter and deployment, not trust in a release number.

| Versioned ID | Level | Topic | State | Evidence and remaining proof |
|---|---|---|---|---|
| v1.01-C10.1.1 | 1 | Component provenance | Partial | H-SUPPLY pins archives/digests; no complete dependency provenance or tamper-rejection run. |
| v1.01-C10.1.2 | 2 | Server selection policy | Not present | AWG is not an MCP client/server-registry policy manager. |
| v1.01-C10.1.3 | 2 | Local server containment | Not present | No MCP-client-launched local server path. Loopback PHP is not a sandbox. |
| v1.01-C10.2.1 | 1 | Per-call token validation | Upstream | H-WP checks anonymous refusal using Application Passwords; no OAuth access-token validation test. |
| v1.01-C10.2.2 | 1 | OAuth claim checking | Upstream | Issuer/audience/expiry/scope cases are not exercised by the reference stack. |
| v1.01-C10.2.3 | 1 | Credential non-persistence | Upstream | W-EVIDENCE excludes payloads; no resource-server token/storage inspection. Harness credentials are intentionally private test configuration. |
| v1.01-C10.2.4 | 2 | Scope-filtered discovery | Partial | H-WP proves hidden-Ability exclusion; not OAuth scope variation or connection-independent discovery. |
| v1.01-C10.2.5 | 2 | Invocation token scope | Partial | W-GATE, H-WP prove WordPress permission/rate denial, not OAuth tool-and-argument scope enforcement. |
| v1.01-C10.2.6 | — | Retired identifier | Removed | Explicitly removed in AISVS 1.01; no inferred replacement or credit. |
| v1.01-C10.2.7 | 1 | Intended token recipient | Upstream | No wrong-audience or downstream token-passthrough test. |
| v1.01-C10.2.8 | 1 | MRTR state authenticity | Upstream | No AWG `requestState` integration. Proposal hashes do not validate MCP state. |
| v1.01-C10.2.9 | 2 | OAuth issuer credential binding | Not present | No persisted OAuth MCP-client identity. |
| v1.01-C10.2.10 | 2 | MRTR state consumption | Upstream | K-REPLAY consumes an AWG approval, not an upstream `requestState` value. |
| v1.01-C10.3.1 | 1 | Remote encrypted transport | Upstream | H-WP is loopback HTTP. Historical HTTPS checks are not current TLS/security configuration proof. |
| v1.01-C10.3.2 | 1 | Controlled STDIO use | Not present | Reference path tests HTTP, not a deployed STDIO boundary. |
| v1.01-C10.3.3 | 1 | Origin and Host validation | Upstream | No independent forged-Origin/Host negative tests in H-WP. |
| v1.01-C10.3.4 | 2 | Protocol downgrade refusal | Partial | H-WP sends the fixed revision; no hostile downgrade/fallback test. Old-initialize rejection tests storage limits, not a client minimum. |
| v1.01-C10.3.5 | 3 | Sender-bound tokens | Upstream | No mTLS or DPoP integration or proof. |
| v1.01-C10.3.6 | 2 | Header-policy revision gate | Not present | No AWG policy-enforcing MCP intermediary. |
| v1.01-C10.4.1 | 1 | Model-facing response validation | Not present | No MCP client/model injection pipeline; B-OUTCOMES validates different, WebMCP fixtures. |
| v1.01-C10.4.2 | 1 | Response injection screening | Not present | No MCP-consuming model or screening evaluation. |
| v1.01-C10.4.3 | 1 | Call parameter rejection | Upstream | H-WP now checks missing/wrong-type/out-of-range synthetic input before callbacks; unknown fields and oversized tool-call arguments remain untested. |
| v1.01-C10.4.4 | 2 | Wire/Ability schema rejection | Upstream | Existing happy-path result assertions do not establish strict MCP schema rejection. |
| v1.01-C10.4.5 | 2 | Transport payload bounds | Partial | H-WP proves one 4-KiB initialization-store limit, not total request/result limits for all transports. |
| v1.01-C10.4.6 | 2 | Signed response freshness | Upstream | No signed response nonce/timestamp verification; action hashes are a different mechanism. |
| v1.01-C10.4.7 | 2 | Local install consent | Not present | No MCP client local-server installation UI. |
| v1.01-C10.4.8 | 3 | Tool-change reapproval | Not present | No MCP client tool snapshot/review manager. |
| v1.01-C10.4.9 | 2 | Proxy client isolation | Not present | No shared-upstream-OAuth proxy. W-CONTEXT is a different cache boundary. |
| v1.01-C10.4.10 | 2 | Endpoint-bound consent | Not present | No MCP client consent store or endpoint-change flow. |
| v1.01-C10.4.11 | 1 | Header annotation rejection | Not present | No MCP client definition validator. |
| v1.01-C10.4.12 | 2 | Mirrored value equality | Upstream | H-WP sends consistent headers/body; no mismatched/encoded-header negative cases. |
| v1.01-C10.4.13 | 2 | Validator work limits | Upstream | No hostile nested-schema or validation-time bound tests. A fixed local action schema is not proof for arbitrary MCP schemas. |
| v1.01-C10.4.14 | 1 | Remote schema resolution | Upstream | No network-canary/SSRF tests against the installed MCP validator or consuming client. |
| v1.01-C10.4.15 | 2 | Unresolved reference denial | Upstream | No unresolved-external-reference negative test; local schema tests do not establish this. |
| v1.01-C10.4.16 | 2 | Response cache isolation | Not present | No MCP client/intermediary response cache. W-CONTEXT caches a decision, not a protocol response. |
| v1.01-C10.4.17 | 2 | Private result caching metadata | Upstream | No authorization-filtered response cache-scope assertion. |
| v1.01-C10.4.18 | 2 | Elicitation trust boundary | Upstream | No reference elicitation flow or initiating/completing-user binding test. |
| v1.01-C10.4.19 | 1 | Elicitation URL privacy | Upstream | No URL-mode elicitation generator or credential/personal-data leak test. |
| v1.01-C10.4.20 | 2 | URL-opening consent | Not present | No MCP client URL presentation, consent, or prefetch behavior. |
| v1.01-C10.4.21 | 2 | Private user interaction | Not present | No client/browser elicitation isolation mechanism. |

## 5. C12 mapping

Inference, RAG, training, and model monitoring are not implemented by the
governance reference. Do not invent those subsystems to improve a table score.
Minimal governance evidence is not equivalent to the telemetry of a complete
AI deployment. In particular, privacy-preserving hashes must not be asserted
to satisfy a requirement for action parameters without a reviewed retrieval,
authorization, and retention design.

| Versioned ID | Level | Topic | State | Evidence and remaining proof |
|---|---|---|---|---|
| v1.01-C12.1.1 | 1 | AI interaction telemetry | Partial | K-EVIDENCE, W-EVIDENCE record governance lifecycle context, not model interaction/session telemetry. |
| v1.01-C12.1.2 | 2 | Safety decision audit | Partial | K-EVIDENCE, W-EVIDENCE capture some policy reasons; no content-moderation/filter audit pipeline. |
| v1.01-C12.1.3 | 2 | Inference event fields | Not present | No model/provider/token-usage instrumentation or interoperable inference-event exporter. |
| v1.01-C12.1.4 | 2 | Retrieval trace | Not present | No RAG retrieval pipeline. |
| v1.01-C12.2.1 | 1 | Attack detection alerts | Not present | A denied action is not a jailbreak/injection detector or alert delivery proof. |
| v1.01-C12.2.2 | 2 | Behavioral anomaly detection | Partial | W-RATE rejects excess invocation counts; no conversation/probing detector or alert evaluation. |
| v1.01-C12.2.3 | 2 | Coordinated attack rules | Not present | No security-event correlation/detection service. |
| v1.01-C12.2.4 | 2 | Extraction investigation metadata | Not present | No model-extraction alert producer. |
| v1.01-C12.2.5 | 2 | Attributed token metrics | Not present | A budget port and per-principal call counter do not meter model tokens. |
| v1.01-C12.2.6 | 3 | LLM traffic covert-channel detection | Not present | No LLM traffic inspection subsystem. |
| v1.01-C12.3.1 | 1 | Statistical input shift | Not present | No statistical model-input drift monitor. |
| v1.01-C12.3.2 | 2 | Factual output detection | Not present | No hallucination evaluation/monitor. |
| v1.01-C12.3.3 | 2 | Output-quality time series | Not present | No model-quality time-series collection. |
| v1.01-C12.3.4 | 3 | Behavioral shift attribution | Not present | No model behavioral baseline or drift attribution. |
| v1.01-C12.4.1 | 2 | Proactive threat evaluation | Partial | K-GATES evaluates supplied policy before an action; no behavior-pattern or threat-landscape analysis. |
| v1.01-C12.4.2 | 2 | Proactive approval audit | Partial | K-EVIDENCE captures fixture approval/lifecycle events; no real proactive approval path or reviewed full-parameter audit access. |
| v1.01-C12.4.3 | 2 | Stop/override audit | Gap | No implemented kill-switch/override event path or test. Tool cancellation alone is different. |
| v1.01-C12.5.1 | 1 | Dataset derivation history | Not present | No training-data lifecycle. |
| v1.01-C12.5.2 | 1 | Labeling history | Not present | No data-labeling workflow. |
| v1.01-C12.5.3 | 2 | Model-change audit | Not present | No model artifact/update manager. |
| v1.01-C12.5.4 | 2 | Ingest provenance | Not present | No document ingestion/knowledge store. |

## 6. Verification backlog

These are proposed follow-up tasks, not work performed by creating this map.
Priority is relative to the next expansion of the reference path. The missing
subsystems above are not all prerequisites to publishing a read-only draft.
Test an existing mechanism first; a test cannot stand in for a missing runtime,
credential issuer, independent reviewer, or durable store.

### 6.1 Bounded next tests on existing paths

| Candidate | Relevant IDs | Observable result needed | Boundary |
|---|---|---|---|
| N1: mutable-gate failure matrix | C9.1.2, C9.5.1, C9.5.3, C9.5.6 | Isolate permission, delegation, budget, precondition, and policy failures at each applicable phase; include exceptions/malformed results and revocation after proposal. Assert no executor call and the correct minimized denial evidence. | Extend the kernel tests without treating mocked checks as real grant/budget services. |
| N2: effect-then-failure recovery | C9.2.8, C12.4.2 | Make an executor record its effect before throwing; prove retry cannot create a second effect, and the caller retains uncertainty. Test original-result lookup separately when a real result store exists. | K-REPLAY currently throws before an effect; in-process concurrency does not prove crash/restart or distributed safety. |
| N3: context and probe accounting | C9.3.9, C9.5.1, C9.5.6 | Vary principal, capability, input, and context independently; no cached allow crosses the boundary and one external invocation spends one budget. November 24 H-WP verifies native Core/MCP normalized-input parity on 7.1.5 / 0.7.2. Changed-value sanitization/revalidation still needs actual 7.2 tests. | W-CONTEXT changes only client identity; storage-tenant isolation remains a separate integration task. |
| N4: native schema/transport negatives | C9.3.2, C10.3.3, C10.4.3-C10.4.5, C10.4.12-C10.4.15 | Against test-only fixtures, exercise wrong/oversized arguments and results, hostile Origin/Host separately, mismatched encoded headers, bounded validation, and rejected external/unresolved schema references. Invalid input must stop before callback; invalid output after dispatch must preserve effect uncertainty. Reject unsafe schema fetches and prevent credential/data leaks. | Inspect the installed Adapter's mechanism and prerequisites first; use the pinned official suite where applicable. App Password tests do not establish OAuth conformance. |
| N5: evidence failure boundaries | C12.1.1, C12.1.2, C12.4.2 | Exercise corrupt/unavailable stores and denied/failed executions; verify correct event meaning, stable correlation, field limits, and absence of proof/secrets across success and failure paths. | W-EVIDENCE tests decision-store failure and projection, not independent durable audit integrity or complete inference telemetry. |
| N6: native WebMCP behavior | C9.3.2; application boundary review | Record a browser build, flags, and WPT revision; verify schema/result behavior, lifecycle, cancellation, and document scope on the existing helper. | Include opaque Tool identity, stale-handle refusal, nullable fields, and source/clone behavior after PR 348. WPT e572c0e06ac3 is reviewed, not run. This is WebMCP compatibility evidence, not a C10 pass or continuation adapter. |

N1-N3 are the first local test candidates. N4-N6 need the actual upstream/host
environment and carefully isolated fixtures. Document actual assertions and
limitations before changing a row; do not convert a candidate into coverage
because a command exits successfully.

### 6.2 Integration prerequisites before widening capability

| Prerequisite | Relevant IDs | Evidence needed before claiming the broader control |
|---|---|---|
| Independent human authorization and review | C9.2.1, C9.2.2, C9.2.8, C9.2.9 | A concrete verifier outside the gated agent's authority; safe complete canonical review; negative tests for changed principal/context/nonce and agent-controlled approval routes; real credential isolation. Preserve RFC 0003's separate assurance boundary. |
| Durable replay and authoritative outcome recovery | C9.2.8, C9.4.4, C12.4.2 | Atomic/idempotent storage, worker/process/restart and lost-response tests, committed-effect reconciliation, and controlled audit retrieval. A request hash, a mutex in one process, or a receipt alone is insufficient. |
| Trusted impact/chain policy | C9.2.3-C9.2.5, C9.2.10, C9.2.11 | Trusted classifier and policy fixtures; independently established cumulative chain; expansion cannot reuse earlier approval. No chain implementation is introduced just to fill this matrix. |
| Resource/stop controls and independent evidence | C9.1.1-C9.1.3, C9.3.1, C9.6.1, C9.6.3, C12.4.3 | Runtime/deployment resource enforcement, cancellation-versus-partial-effect accounting, independent stop channel, and auditable stop/override events. Registration removal is not a process kill. |
| Real MCP/OAuth ownership | C10 upstream rows | Select the deployed transport/authentication contract and owner; validate OAuth, TLS, cache, elicitation, and response-signing features only where present. Unsupported features remain explicit gaps/absent roles, not simulated passes. |

Model, RAG, training, AI-review, client-installation, and inter-agent controls
remain outside the current implementation scope. A consuming application may
need them; its owner must justify applicability using its actual architecture.
They are not a request to add those products to AWG.

## 7. Validation and maintenance

Local checks completed on 2026-11-15:

- all 98 matrix identifiers and levels match the pinned source, with no missing
  or duplicate entries, including the removed identifier;
- all 54 literal catalogue lookup strings exist in the cited tests/fixtures,
  and relative document links resolve; these are not 54 additional tests;
- `git diff --check` and `npm test` passed, including 19 kernel, 20 WordPress,
  and 18 WebMCP cases plus action/lifecycle and cross-runtime fixtures.

For this map, rerun `git diff --check` and `npm test`. The latter covers the
existing kernel, action/lifecycle fixtures, WordPress unit boundary, and fake
WebMCP host. It does not run WordPress E2E, WPT, the official MCP conformance
suite, or an AISVS audit. Separately, the November 24 isolated WordPress E2E
returned `ACCEPTED` for 7.1.5 / 0.7.2. This bounded result does not change any
matrix state or establish an AISVS pass. N3/N4 gain only the input subset above.

For an update:

1. Pin both the AISVS edition/commit and the AWG implementation/test snapshot.
2. Compare source identifiers and levels, including retired entries; report
   the selected chapter scope and retain an explicit row for every entry.
3. Inspect assertions and actual runtime code, not only test names or docs.
4. Record the command, environment, observed result, owner, and remaining limits.
5. Reassess absent roles when a subsystem is introduced; do not inherit credit
   from the old scope or from an upstream feature announcement.
6. Keep any full compliance assessment separate from this partial evidence map.

See [REFERENCES.md](../REFERENCES.md) for source maturity and monitoring,
[RFC 0003](../rfcs/0003-human-authorization-assurance.md) for human provenance,
and the [WordPress 7.2 candidate](WORDPRESS.md#42-wordpress-72-validation-candidate)
for the current input-preparation integration review.
