# Japan Governance Profile

**Profile ID:** `AGW-JP-0.1`  
**Status:** Experimental  
**Last reviewed:** 2026-08-25

## 1. Purpose and limits

This optional profile maps Japanese AI-safety, privacy, procurement, and
contract-management concerns to controls and evidence in the Agentic Web
Governance core.

It is intended to help Japanese organizations ask better operational questions
when agents can publish, modify, disclose, or trigger actions through a web
system.

This profile:

- is technical guidance, not legal advice;
- does not determine whether a deployment complies with Japanese law;
- is not an official control catalogue;
- is not endorsed or certified by a Japanese government body;
- must be reviewed against current sources and an organization's actual use.

The control identifiers below are project identifiers.

## 2. Source context

The profile currently tracks:

- Japan AISI, *Guide to Evaluation Perspectives on AI Safety*, Version 1.20,
  published 2026-07-07, including the AI-agent-specific perspective of
  observation and control;
- MIC and METI, *AI Guidelines for Business*, Version 1.2, published in 2026;
- Personal Information Protection Commission guidance concerning use of
  generative AI services;
- METI, *Checklist for Contracts on the Use and Development of AI*, published
  2025-02-18;
- other applicable privacy, procurement, security, and organization-specific
  requirements selected by adopters.

Exact links and review dates are maintained in
[../REFERENCES.md](../REFERENCES.md). Source guidance evolves; implementations
must record the source version used by a policy.

## 3. Profile controls

| ID | Control | Core mechanism | Minimum evidence |
|---|---|---|---|
| `JP-GOV-01` | Maintain an inventory of agent-accessible actions | Capability inventory and governance state | Capability, owner, risk, last review |
| `JP-GOV-02` | Observe autonomous action | Action lifecycle and evidence | Proposal, decision, execution outcome |
| `JP-GOV-03` | Retain human control and a stop mechanism | Approval, revocation, emergency disable | Decision, approver, revocation/disable event |
| `JP-GOV-04` | Record interaction with external systems | External-transmission policy | Recipient, purpose, data class, outcome |
| `JP-GOV-05` | Classify personal and sensitive data | Data classes and field/resource metadata | Applied classes and classification source |
| `JP-GOV-06` | Restrict external transmission | Separate disclosure decision | Allow/deny reason, recipient, minimization |
| `JP-GOV-07` | Maintain provider/model governance metadata | Provider registry and policy | Provider/model, review state, policy version |
| `JP-GOV-08` | Define approval responsibility | Approval routing and authorization | Required role, actual approver, timestamp |
| `JP-GOV-09` | Define evidence retention | Retention classes | Duration, deletion rule, responsible role |
| `JP-GOV-10` | Support incident evidence export | Controlled evidence export | Scope, redaction profile, digest, exporter |
| `JP-GOV-11` | Record relevant contract metadata | Provider and purpose metadata | Review date, data-use notes, owner |
| `JP-GOV-12` | Version and periodically review policy | Immutable policy versions | Version, source versions, reviewer, date |

## 4. Observation and control

For agent systems capable of affecting WordPress or another external
environment, organizations SHOULD be able to determine:

```text
What actions are available?
Which actions are enabled for agents?
Who authorized the acting principal?
Which agent and client claims are known?
What limits apply?
Which actions require review?
Can pending or running work be stopped?
What happened after execution?
```

Implementations supporting this profile SHOULD provide:

- action and capability inventory;
- risk and data classification;
- policy allow/deny controls;
- human approval thresholds;
- tool-call, iteration, and cost budgets;
- visibility into pending and running actions;
- emergency disable and delegation revocation;
- post-action evidence review.

The profile does not assume that every running external action can be cancelled.
When cancellation is unavailable, the implementation must document that limit
and any compensating action.

## 5. Personal information and external transmission

WordPress and similar systems may contain inquiry data, member or customer
records, profiles, orders, unpublished content, and private documents.

Permission to read those records does not imply permission to disclose them to
an AI provider or another service.

Before transmission, policy SHOULD evaluate:

- applicable data classes;
- whether personal or sensitive fields are included;
- the provider, model, endpoint, and purpose;
- whether the payload can be minimized or redacted;
- organization notes about retention, training use, and processing region;
- contract or procurement review state;
- whether human approval is required.

Evidence SHOULD record metadata and hashes instead of duplicating the personal
data that the policy is intended to protect.

## 6. Provider and contract metadata

An optional organization registry MAY record:

```text
provider_id
approved_services_or_models
approved_purposes
allowed_data_classes
input-use_note
output-use_note
training-use_note
retention_note
processing-region_note
contract_reviewed_at
review_owner
organization_status
source_or_contract_reference
```

This registry contains organization assertions and review notes. It MUST NOT
present them as independent verification unless a separate verification process
exists.

## 7. Japanese review summary

For consequential actions, a Japanese-language review UI SHOULD present at
least:

```text
実行主体
代理元となる利用者・組織
対象
実行する操作
変更内容
扱うデータ
外部送信の有無と送信先
影響範囲
取り消し・停止の可否
リスク区分
適用ポリシー
必要な承認
承認の有効期限
```

These labels are presentation guidance. Implementations must bind the review to
the structured proposal and request hash described by the core specification.

## 8. Suggested policy defaults

An implementation using this profile SHOULD default to:

- agent-facing capabilities disabled until inventoried;
- no unauthenticated access to non-public data;
- separate policy for every external recipient;
- approval for public publishing, personal-data transmission, destructive
  changes, identity changes, and financial actions;
- strong authentication for sensitive administration and financial approval;
- evidence export restricted to authorized roles;
- explicit retention periods and review dates;
- null identity fields when agent or client identity is not established.

## 9. Example profile flow

```text
Agent proposes a WordPress Ability call
        |
        v
Application permission check
        |
        v
Core policy and delegation
        |
        v
Japan Profile checks
  personal data?
  external recipient?
  provider reviewed?
  observation/control available?
  responsible approver?
        |
        +--> DENY
        +--> REQUIRE_APPROVAL / REQUIRE_STRONG_AUTH
        +--> ALLOW
        |
        v
Execution-time re-check
        |
        v
Execution and evidence
```

## 10. Evidence extension

This profile MAY add:

```text
profile_id
profile_version
source_versions[]
organization_control_ids[]
provider_review_state?
contract_review_reference?
responsible_role?
personal_data_involved?
observation_control_state?
```

Legal conclusions and unnecessary personal data MUST NOT be placed in evidence
records merely because this profile is enabled.

## 11. Claims to avoid

Implementations and documentation MUST NOT claim, solely from this profile:

- AI Act compliant;
- APPI compliant;
- AISI certified;
- government approved;
- guaranteed legal compliance;
- complete prevention of prompt injection or autonomous-agent harm.

Preferred wording:

> Provides configurable technical controls and evidence that can support an
> organization's AI governance process.

## 12. Maintenance

Every release of this profile SHOULD record:

- source title and publisher;
- source version and publication date;
- profile review date;
- reviewer role;
- mapping changes;
- unresolved interpretation questions.

Material source changes require an RFC or a clearly marked profile revision.
