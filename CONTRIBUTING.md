# Contributing to Agentic Web Governance

Agentic Web Governance is currently a Draft 0.x specification. Contributions
should keep normative requirements, implementation evidence, and research
hypotheses clearly separated.

## Choose the appropriate path

| Change | Process |
|---|---|
| Typo, wording, source update, or non-normative clarification | Open a focused pull request |
| Normative requirement, security invariant, profile, mapping, or governance change | Submit an RFC under `rfcs/` |
| Research claim or implementation evidence | Cite a primary source and state its confidence or implementation status |
| Future reference implementation code | Include focused tests and document the specification requirement it exercises |
| Suspected vulnerability | Follow `SECURITY.md`; do not open a public issue with sensitive details |

Use the next available four-digit number and `rfcs/0000-template.md` for an
RFC. A draft RFC may be incomplete, but it must identify its unresolved
security, privacy, compatibility, and conformance questions.

## Pull requests

- Keep each change focused and explain whether it is normative.
- Update affected mappings, profiles, threats, evidence requirements, and
  references when their claims change.
- Prefer primary standards, upstream documentation, and reproducible evidence.
- Do not present proposed or experimental behavior as implemented or stable.
- Add or update tests when executable behavior is introduced.
- Confirm that Markdown links and code fences remain valid.
- Do not include credentials, personal data, private incident details, or
  confidential provider material.

Contributions are licensed according to `LICENSE.md` unless explicitly stated
otherwise.

## Reciprocity and stewardship

Commercial use is welcome. The project also asks organizations and individuals
that derive sustained value from it to help preserve the shared infrastructure
on which that value depends. Meaningful stewardship may include:

- contributing generally useful fixes and compatibility improvements upstream;
- reporting security, privacy, and interoperability findings responsibly;
- preserving applicable attribution and license notices;
- supporting maintenance through review, documentation, testing, funding, or
  other resources when the project becomes a material dependency; and
- avoiding claims of project endorsement, certification, or official
  compatibility unless they have been expressly granted.

These are community expectations, not additional license conditions. They do
not limit the permissions granted by the applicable CC BY 4.0 or
GPL-2.0-or-later license. They describe the reciprocal conduct that this project
considers responsible participation in a shared technical commons.

## Maintainers and decisions

During Draft 0.x, `@at-shift` is the initial maintainer and records project
decisions in the relevant pull request or RFC.

An RFC becomes `ACCEPTED` when the maintainer approves it and records the
rationale. An unresolved objection that identifies a violation of a core
security invariant blocks acceptance. Other objections must be answered in the
decision record, including why a rejected alternative was not selected.

Accepted decisions may be revisited through a later RFC. Changes to this
decision process also require an RFC.

## Specification maturity

- `DRAFT`: requirements may change and conformance is not claimed.
- `CANDIDATE`: the intended version scope is complete and ready for public
  implementation feedback.
- `STABLE`: the version has an accepted promotion RFC, no unresolved
  publication blockers, completed security and reference review, a conformance
  test plan or fixtures, at least 30 days of public review, and a tagged release.

Stable status does not certify an implementation or guarantee legal
compliance.

## Review conduct

Discuss proposals on their technical, security, privacy, interoperability, and
operational merits. Be specific, assume good faith, disclose relevant
conflicts, and leave a reviewable record of consequential decisions.
