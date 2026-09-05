// SPDX-License-Identifier: GPL-2.0-or-later

import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import Ajv2020 from "ajv/dist/2020.js";

import { createDigest } from "../src/javascript/canonical-action-v1.mjs";
import { ReferenceGovernanceKernel } from "../src/javascript/reference-kernel.mjs";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const actionManifest = JSON.parse(
  await readFile(resolve(root, "fixtures/action-v1/manifest.json"), "utf8"),
);
const actionSchema = JSON.parse(
  await readFile(resolve(root, "schemas/action-v1.schema.json"), "utf8"),
);
const validateAction = new Ajv2020({ allErrors: true, strict: true }).compile(
  actionSchema,
);

function clone(value) {
  return structuredClone(value);
}

function makeHarness(overrides = {}) {
  const state = {
    now: 2_000_000_100,
    applicationPermission: true,
    delegation: true,
    policyDecision: "REQUIRE_APPROVAL",
    budget: true,
    preconditions: true,
    approverAuthorized: true,
    humanAuthorizationVerified: true,
    humanAuthorizationThrows: false,
    humanAuthorizationMethod: "reference-independent-boundary",
    expectedHumanAuthorizationProof: "reference-human-authorization-proof",
    strongAuthenticationVerified: true,
    policyReasonCodes: undefined,
    currentAction: clone(actionManifest.base_action),
    sideEffectCount: 0,
    executorThrows: false,
    executorDelayMs: 0,
    ...overrides,
  };
  const counters = new Map();
  const idFactory = (kind) => {
    const next = (counters.get(kind) ?? 0) + 1;
    counters.set(kind, next);
    return `${kind}-${next}`;
  };

  const kernel = new ReferenceGovernanceKernel({
    hashAction: (action) =>
      createDigest(action, actionManifest.domain_prefix, validateAction),
    checkApplicationPermission: async () => state.applicationPermission,
    checkDelegation: async () => state.delegation,
    evaluatePolicy: async ({ action }) => ({
      decision: state.policyDecision,
      reason_codes:
        state.policyReasonCodes ??
        (state.policyDecision === "ALLOW"
          ? ["REFERENCE_POLICY_ALLOW"]
          : state.policyDecision === "DENY"
            ? ["REFERENCE_POLICY_DENY"]
            : state.policyDecision === "REQUIRE_STRONG_AUTH"
              ? ["REFERENCE_STRONG_AUTH"]
              : ["REFERENCE_APPROVAL_REQUIRED"]),
      policy_id: action.policy.id,
      policy_version: action.policy.version,
      matched_rules: ["reference-rule"],
      required_controls:
        state.policyDecision === "REQUIRE_STRONG_AUTH"
          ? ["approval", "strong_auth"]
          : state.policyDecision === "REQUIRE_APPROVAL"
            ? ["approval"]
            : [],
    }),
    checkBudget: async () => state.budget,
    checkPreconditions: async () => state.preconditions,
    authorizeApprover: async () => state.approverAuthorized,
    verifyHumanAuthorization: async ({ proposal, approval, evidence }) => {
      if (state.humanAuthorizationThrows) {
        throw new Error("authorization verification unavailable");
      }
      const bound =
        state.humanAuthorizationVerified &&
        evidence?.proof === state.expectedHumanAuthorizationProof &&
        evidence?.request_hash === proposal.request_hash &&
        evidence?.approver?.type === approval.approver.type &&
        evidence?.approver?.id === approval.approver.id &&
        evidence?.decision === approval.decision &&
        evidence?.method === "reference-independent-boundary" &&
        evidence?.decided_at === approval.decided_at &&
        evidence?.expires_at === approval.expires_at &&
        evidence?.single_use === approval.single_use;
      return bound
        ? { verified: true, method: state.humanAuthorizationMethod }
        : { verified: false };
    },
    verifyStrongAuthentication: async () =>
      state.strongAuthenticationVerified,
    reconstructAction: async () => clone(state.currentAction),
    executeAction: async () => {
      if (state.executorDelayMs > 0) {
        await new Promise((resolveDelay) =>
          setTimeout(resolveDelay, state.executorDelayMs),
        );
      }
      if (state.executorThrows) {
        throw new Error("application failure must not enter evidence");
      }
      state.sideEffectCount += 1;
      return { resource_refs: ["post:42"] };
    },
    now: () => state.now,
    idFactory,
  });

  return { kernel, state };
}

async function approve(
  kernel,
  proposal,
  state,
  authentication = "normal",
  evidenceOverrides = {},
) {
  const approver = { type: "wordpress-user", id: "501" };
  const expiresAt = state.now + 300;
  return kernel.recordApproval(proposal.proposal_id, {
    approver,
    authorization_source: "reference-reviewer-policy",
    decision: "approved",
    expires_at: expiresAt,
    single_use: true,
    authentication,
    authorization_evidence: {
      proof: state.expectedHumanAuthorizationProof,
      request_hash: proposal.request_hash,
      approver: clone(approver),
      decision: "approved",
      method: "reference-independent-boundary",
      decided_at: state.now,
      expires_at: expiresAt,
      single_use: true,
      ...evidenceOverrides,
    },
  });
}

const tests = [
  [
    "approved proposal executes once and records sanitized evidence",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      assert.equal(proposal.status, "pending_approval");

      const approval = await approve(kernel, proposal, state);
      assert.equal(approval.status, "recorded");
      assert.deepEqual(approval.approval.authorization_assurance, {
        method: "reference-independent-boundary",
        verified: true,
      });
      assert.equal(approval.approval.strong_auth_verified, false);
      assert.equal(
        Object.hasOwn(approval.approval, "authorization_evidence"),
        false,
      );

      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.status, "executed");
      assert.equal(execution.reason_code, "EXECUTE_ALLOWED");
      assert.equal(execution.replay_state, "used");
      assert.equal(state.sideEffectCount, 1);

      const replay = await kernel.execute(proposal.proposal_id);
      assert.equal(replay.reason_code, "APPROVAL_REPLAYED");
      assert.equal(state.sideEffectCount, 1);

      const evidence = kernel.listEvidence();
      assert.deepEqual(
        evidence.map((event) => event.event_type),
        [
          "action.proposed",
          "approval.requested",
          "approval.approved",
          "execution.started",
          "execution.succeeded",
          "approval.rejected",
        ],
      );
      for (const event of evidence) {
        assert.equal(Object.hasOwn(event, "action"), false);
        assert.equal(Object.hasOwn(event, "arguments"), false);
      }
      assert.equal(
        JSON.stringify({ approval, evidence }).includes(
          state.expectedHumanAuthorizationProof,
        ),
        false,
      );
    },
  ],
  [
    "application denial cannot be widened by policy",
    async () => {
      const { kernel, state } = makeHarness({
        applicationPermission: false,
        policyDecision: "ALLOW",
      });
      const proposal = await kernel.propose(actionManifest.base_action);
      assert.equal(proposal.status, "denied");
      assert.equal(proposal.reason_code, "APPLICATION_PERMISSION_DENIED");
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "every explicit mutable gate fails closed",
    async () => {
      const scenarios = [
        [{ delegation: false, policyDecision: "ALLOW" }, "DELEGATION_INVALID"],
        [{ budget: false, policyDecision: "ALLOW" }, "BUDGET_EXHAUSTED"],
        [{ policyDecision: "INVALID" }, "POLICY_INVALID"],
        [
          { preconditions: false, policyDecision: "ALLOW" },
          "PRECONDITION_FAILED",
        ],
      ];

      for (const [overrides, expectedReason] of scenarios) {
        const { kernel, state } = makeHarness(overrides);
        const proposal = await kernel.propose(actionManifest.base_action);
        const outcome =
          proposal.status === "ready"
            ? await kernel.execute(proposal.proposal_id)
            : proposal;
        assert.equal(outcome.reason_code, expectedReason);
        assert.equal(state.sideEffectCount, 0);
      }
    },
  ],
  [
    "a restrictive policy always has a stable fallback reason",
    async () => {
      const { kernel } = makeHarness({
        policyDecision: "DENY",
        policyReasonCodes: [],
      });
      const proposal = await kernel.propose(actionManifest.base_action);
      assert.equal(proposal.status, "denied");
      assert.equal(proposal.reason_code, "POLICY_DENIED");
    },
  ],
  [
    "missing approval remains pending without reconstruction or side effects",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.reason_code, "APPROVAL_REQUIRED");
      assert.equal(execution.reconstruction, "not_attempted");
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "an unauthorized reviewer cannot create an approval",
    async () => {
      const { kernel, state } = makeHarness({ approverAuthorized: false });
      const proposal = await kernel.propose(actionManifest.base_action);
      const approval = await approve(kernel, proposal, state);
      assert.equal(approval.reason_code, "APPROVER_NOT_AUTHORIZED");
      assert.equal(kernel.getApproval(proposal.proposal_id), undefined);
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "authorization source metadata without independent evidence fails closed",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      const approval = await kernel.recordApproval(proposal.proposal_id, {
        approver: { type: "wordpress-user", id: "501" },
        authorization_source: "reference-reviewer-policy",
        decision: "approved",
        expires_at: state.now + 300,
        single_use: true,
        authentication: "normal",
      });

      assert.equal(approval.reason_code, "HUMAN_AUTHORIZATION_UNVERIFIED");
      assert.equal(kernel.getApproval(proposal.proposal_id), undefined);
      assert.equal(
        kernel.getProposal(proposal.proposal_id).status,
        "pending_approval",
      );
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "human authorization verifier denial, malformed result, and failure fail closed",
    async () => {
      for (const overrides of [
        { humanAuthorizationVerified: false },
        { humanAuthorizationMethod: "" },
        { humanAuthorizationThrows: true },
      ]) {
        const { kernel, state } = makeHarness(overrides);
        const proposal = await kernel.propose(actionManifest.base_action);
        const approval = await approve(kernel, proposal, state);

        assert.equal(approval.reason_code, "HUMAN_AUTHORIZATION_UNVERIFIED");
        assert.equal(kernel.getApproval(proposal.proposal_id), undefined);
        assert.equal(state.sideEffectCount, 0);
      }
    },
  ],
  [
    "human authorization evidence is bound to the exact decision context",
    async () => {
      const mutations = [
        () => ({ request_hash: `sha256:${"0".repeat(64)}` }),
        () => ({
          approver: { type: "wordpress-user", id: "another-reviewer" },
        }),
        () => ({ decision: "denied" }),
        () => ({ method: "caller-asserted-method" }),
        (state) => ({ decided_at: state.now - 1 }),
        (state) => ({ expires_at: state.now + 301 }),
        (state) => ({ single_use: false }),
      ];

      for (const mutate of mutations) {
        const { kernel, state } = makeHarness();
        const proposal = await kernel.propose(actionManifest.base_action);
        const approval = await approve(
          kernel,
          proposal,
          state,
          "normal",
          mutate(state),
        );

        assert.equal(approval.reason_code, "HUMAN_AUTHORIZATION_UNVERIFIED");
        assert.equal(kernel.getApproval(proposal.proposal_id), undefined);
        assert.equal(state.sideEffectCount, 0);
      }
    },
  ],
  [
    "a verified human rejection is recorded without retaining raw evidence",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      const approver = { type: "wordpress-user", id: "501" };
      const expiresAt = state.now + 300;
      const rejection = await kernel.recordApproval(proposal.proposal_id, {
        approver,
        authorization_source: "reference-reviewer-policy",
        decision: "denied",
        expires_at: expiresAt,
        single_use: true,
        authentication: "normal",
        authorization_evidence: {
          proof: state.expectedHumanAuthorizationProof,
          request_hash: proposal.request_hash,
          approver: clone(approver),
          decision: "denied",
          method: "reference-independent-boundary",
          decided_at: state.now,
          expires_at: expiresAt,
          single_use: true,
        },
      });

      assert.equal(rejection.status, "recorded");
      assert.equal(rejection.approval.decision, "denied");
      assert.equal(rejection.approval.authorization_assurance.verified, true);
      assert.equal(
        JSON.stringify({ rejection, evidence: kernel.listEvidence() }).includes(
          state.expectedHumanAuthorizationProof,
        ),
        false,
      );
      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.reason_code, "APPROVAL_NOT_APPROVED");
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "changed action cannot reuse an exact-request approval",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      await approve(kernel, proposal, state);
      state.currentAction.arguments.status = "draft";

      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.reason_code, "ACTION_HASH_MISMATCH");
      assert.equal(execution.reconstruction, "mismatched");
      assert.equal(state.sideEffectCount, 0);
      assert.equal(kernel.getApproval(proposal.proposal_id).replay_state, "unused");
    },
  ],
  [
    "execution-time permission revocation fails closed",
    async () => {
      const { kernel, state } = makeHarness({ policyDecision: "ALLOW" });
      const proposal = await kernel.propose(actionManifest.base_action);
      assert.equal(proposal.status, "ready");
      state.applicationPermission = false;

      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.reason_code, "APPLICATION_PERMISSION_DENIED");
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "a stronger execution-time policy requires a new approval",
    async () => {
      const { kernel, state } = makeHarness({ policyDecision: "ALLOW" });
      const proposal = await kernel.propose(actionManifest.base_action);
      state.policyDecision = "REQUIRE_APPROVAL";

      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.reason_code, "APPROVAL_REQUIRED");
      assert.equal(execution.reconstruction, "matched");
      assert.equal(kernel.getProposal(proposal.proposal_id).status, "pending_approval");

      const approval = await approve(kernel, proposal, state);
      assert.equal(approval.status, "recorded");
      const resumed = await kernel.execute(proposal.proposal_id);
      assert.equal(resumed.status, "executed");
      assert.equal(state.sideEffectCount, 1);
    },
  ],
  [
    "an expired approval can be replaced without weakening the proposal",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      await approve(kernel, proposal, state);
      state.now += 300;

      const expired = await kernel.execute(proposal.proposal_id);
      assert.equal(expired.reason_code, "APPROVAL_EXPIRED");
      assert.equal(kernel.getProposal(proposal.proposal_id).status, "pending_approval");

      const replacement = await approve(kernel, proposal, state);
      assert.equal(replacement.status, "recorded");
      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.status, "executed");
      assert.equal(state.sideEffectCount, 1);
    },
  ],
  [
    "strong-auth policy rejects a normal approval",
    async () => {
      const { kernel, state } = makeHarness({
        policyDecision: "REQUIRE_STRONG_AUTH",
      });
      const proposal = await kernel.propose(actionManifest.base_action);
      const normalApproval = await approve(kernel, proposal, state);
      assert.equal(normalApproval.reason_code, "STRONG_AUTH_REQUIRED");

      const strongApproval = await approve(kernel, proposal, state, "strong");
      assert.equal(strongApproval.status, "recorded");
      assert.equal(strongApproval.approval.strong_auth_verified, true);
      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.status, "executed");
      assert.equal(state.sideEffectCount, 1);
    },
  ],
  [
    "a self-asserted strong-auth label is not sufficient",
    async () => {
      const { kernel, state } = makeHarness({
        policyDecision: "REQUIRE_STRONG_AUTH",
        strongAuthenticationVerified: false,
      });
      const proposal = await kernel.propose(actionManifest.base_action);
      const approval = await approve(kernel, proposal, state, "strong");
      assert.equal(approval.reason_code, "STRONG_AUTH_REQUIRED");
      assert.equal(kernel.getApproval(proposal.proposal_id), undefined);
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "a later strong-auth policy does not trust an earlier strong label",
    async () => {
      const { kernel, state } = makeHarness();
      const proposal = await kernel.propose(actionManifest.base_action);
      const approval = await approve(kernel, proposal, state, "strong");
      assert.equal(approval.status, "recorded");
      assert.equal(approval.approval.strong_auth_verified, false);

      state.policyDecision = "REQUIRE_STRONG_AUTH";
      const execution = await kernel.execute(proposal.proposal_id);
      assert.equal(execution.reason_code, "STRONG_AUTH_REQUIRED");
      assert.equal(
        kernel.getProposal(proposal.proposal_id).status,
        "pending_approval",
      );
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "executor failure consumes a single-use approval before retry",
    async () => {
      const { kernel, state } = makeHarness({ executorThrows: true });
      const proposal = await kernel.propose(actionManifest.base_action);
      await approve(kernel, proposal, state);

      const failed = await kernel.execute(proposal.proposal_id);
      assert.equal(failed.status, "failed");
      assert.equal(failed.reason_code, "EXECUTION_FAILED");
      assert.equal(failed.replay_state, "used");

      state.executorThrows = false;
      const retry = await kernel.execute(proposal.proposal_id);
      assert.equal(retry.reason_code, "APPROVAL_REPLAYED");
      assert.equal(state.sideEffectCount, 0);
    },
  ],
  [
    "concurrent execution cannot spend one approval twice",
    async () => {
      const { kernel, state } = makeHarness({ executorDelayMs: 20 });
      const proposal = await kernel.propose(actionManifest.base_action);
      await approve(kernel, proposal, state);

      const outcomes = await Promise.all([
        kernel.execute(proposal.proposal_id),
        kernel.execute(proposal.proposal_id),
      ]);
      assert.deepEqual(
        outcomes.map((outcome) => outcome.reason_code).sort(),
        ["APPROVAL_REPLAYED", "EXECUTE_ALLOWED"],
      );
      assert.equal(state.sideEffectCount, 1);
    },
  ],
];

let failures = 0;
for (const [name, test] of tests) {
  try {
    await test();
  } catch (error) {
    failures += 1;
    console.error(`FAIL reference/${name}: ${error.stack ?? error.message}`);
  }
}

console.log(`Reference kernel JavaScript: ${tests.length} vertical-path cases`);
if (failures > 0) {
  process.exitCode = 1;
}
