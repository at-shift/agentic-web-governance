// SPDX-License-Identifier: GPL-2.0-or-later

import { readFile } from "node:fs/promises";

import Ajv2020 from "ajv/dist/2020.js";

import { createActionHasher } from "../src/javascript/canonical-action-v1.mjs";
import { ReferenceGovernanceKernel } from "../src/javascript/reference-kernel.mjs";

const schema = JSON.parse(
  await readFile(new URL("../schemas/action-v1.schema.json", import.meta.url)),
);
const validate = new Ajv2020({ allErrors: true, strict: true }).compile(schema);
const hashAction = createActionHasher({ validate });
const action = {
  version: 1,
  application: { type: "reference-app", instance_id: "example-1" },
  application_principal: { type: "reference-user", id: "42" },
  capability: "content/publish",
  arguments: { content_id: "post-7", status: "publish" },
  resource: { type: "post", id: "post-7" },
  impact: {
    risk_class: "write",
    reversibility: "reversible",
    data_classes: ["INTERNAL"],
  },
  policy: { id: "reference-policy", version: "1" },
  preconditions: { resource_version: "3" },
};

let now = 2_000_000_000;
let sideEffectCount = 0;
const counters = new Map();
const kernel = new ReferenceGovernanceKernel({
  hashAction,
  checkApplicationPermission: async () => true,
  checkDelegation: async () => true,
  evaluatePolicy: async ({ action: currentAction }) => ({
    decision: "REQUIRE_APPROVAL",
    reason_codes: ["WRITE_REQUIRES_REVIEW"],
    policy_id: currentAction.policy.id,
    policy_version: currentAction.policy.version,
    matched_rules: ["review-writes"],
    required_controls: ["approval"],
  }),
  checkBudget: async () => true,
  checkPreconditions: async () => true,
  authorizeApprover: async ({ approval }) =>
    approval.approver.type === "reference-reviewer",
  verifyStrongAuthentication: async () => false,
  reconstructAction: async () => structuredClone(action),
  executeAction: async () => {
    sideEffectCount += 1;
    return { resource_refs: ["post:post-7"] };
  },
  now: () => now,
  idFactory: (kind) => {
    const next = (counters.get(kind) ?? 0) + 1;
    counters.set(kind, next);
    return `${kind}-${next}`;
  },
});

const proposal = await kernel.propose(action);
now += 10;
const approval = await kernel.recordApproval(proposal.proposal_id, {
  approver: { type: "reference-reviewer", id: "reviewer-9" },
  authorization_source: "reference-reviewer-policy",
  decision: "approved",
  expires_at: now + 300,
  single_use: true,
  authentication: "normal",
});
now += 10;
const execution = await kernel.execute(proposal.proposal_id);

console.log(
  JSON.stringify(
    {
      proposal_status: proposal.status,
      approval_status: approval.status,
      execution_status: execution.status,
      reason_code: execution.reason_code,
      side_effect_count: sideEffectCount,
      evidence_events: kernel
        .listEvidence()
        .map(({ event_type: eventType }) => eventType),
    },
    null,
    2,
  ),
);
