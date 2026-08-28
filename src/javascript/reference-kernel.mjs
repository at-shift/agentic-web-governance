// SPDX-License-Identifier: GPL-2.0-or-later

import { randomUUID, timingSafeEqual } from "node:crypto";

const HASH_PATTERN = /^sha256:[0-9a-f]{64}$/;
const POLICY_DECISIONS = new Set([
  "ALLOW",
  "DENY",
  "REQUIRE_APPROVAL",
  "REQUIRE_STRONG_AUTH",
]);

function clone(value) {
  return value === undefined ? undefined : structuredClone(value);
}

function isNonEmptyString(value) {
  return typeof value === "string" && value.length > 0;
}

function requirePort(name, value) {
  if (typeof value !== "function") {
    throw new TypeError(`${name} port must be a function`);
  }
  return value;
}

function hashesEqual(left, right) {
  if (!HASH_PATTERN.test(left) || !HASH_PATTERN.test(right)) {
    return false;
  }
  return timingSafeEqual(Buffer.from(left, "ascii"), Buffer.from(right, "ascii"));
}

function stringList(value, fallback = []) {
  if (
    !Array.isArray(value) ||
    value.some((item) => !isNonEmptyString(item)) ||
    (value.length === 0 && fallback.length > 0)
  ) {
    return [...fallback];
  }
  return [...new Set(value)];
}

function publicProposal(proposal) {
  return clone({
    id: proposal.id,
    status: proposal.status,
    created_at: proposal.created_at,
    request_hash: proposal.request_hash,
    canonical_action_version: 1,
    decision: proposal.decision,
    action: proposal.action,
  });
}

export class ReferenceGovernanceKernel {
  #ports;
  #now;
  #idFactory;
  #proposals = new Map();
  #approvals = new Map();
  #evidence = [];
  #ids = new Set();
  #locks = new Map();

  constructor({
    hashAction,
    checkApplicationPermission,
    checkDelegation,
    evaluatePolicy,
    checkBudget,
    checkPreconditions,
    authorizeApprover,
    verifyStrongAuthentication,
    reconstructAction,
    executeAction,
    now = () => Math.floor(Date.now() / 1000),
    idFactory = (kind) => `${kind}-${randomUUID()}`,
  }) {
    this.#ports = {
      hashAction: requirePort("hashAction", hashAction),
      checkApplicationPermission: requirePort(
        "checkApplicationPermission",
        checkApplicationPermission,
      ),
      checkDelegation: requirePort("checkDelegation", checkDelegation),
      evaluatePolicy: requirePort("evaluatePolicy", evaluatePolicy),
      checkBudget: requirePort("checkBudget", checkBudget),
      checkPreconditions: requirePort("checkPreconditions", checkPreconditions),
      authorizeApprover: requirePort("authorizeApprover", authorizeApprover),
      verifyStrongAuthentication: requirePort(
        "verifyStrongAuthentication",
        verifyStrongAuthentication,
      ),
      reconstructAction: requirePort("reconstructAction", reconstructAction),
      executeAction: requirePort("executeAction", executeAction),
    };
    this.#now = requirePort("now", now);
    this.#idFactory = requirePort("idFactory", idFactory);
  }

  async propose(action) {
    const proposalId = this.#newId("proposal");
    let digest;
    try {
      digest = await this.#ports.hashAction(clone(action));
    } catch {
      return {
        status: "denied",
        reason_code: "ACTION_INVALID",
        reason_codes: ["ACTION_INVALID"],
        proposal_id: proposalId,
      };
    }
    if (!HASH_PATTERN.test(digest?.requestHash ?? "")) {
      return {
        status: "denied",
        reason_code: "ACTION_INVALID",
        reason_codes: ["ACTION_INVALID"],
        proposal_id: proposalId,
      };
    }

    const proposal = {
      id: proposalId,
      status: "evaluating",
      created_at: this.#timestamp(),
      request_hash: digest.requestHash,
      action: clone(action),
      decision: undefined,
    };
    this.#proposals.set(proposal.id, proposal);
    this.#recordEvidence(proposal, "action.proposed");

    const evaluation = await this.#evaluateMutableGates(proposal, "proposal");
    proposal.decision = evaluation.policy;
    if (!evaluation.allowed) {
      proposal.status = "denied";
      this.#recordEvidence(proposal, "policy.denied", {
        decision: "DENY",
        reason_codes: evaluation.reason_codes,
      });
      return this.#outcome(proposal, "denied", evaluation.reason_codes);
    }

    if (evaluation.policy.decision === "DENY") {
      proposal.status = "denied";
      this.#recordEvidence(proposal, "policy.denied", {
        decision: "DENY",
        reason_codes: evaluation.policy.reason_codes,
      });
      return this.#outcome(
        proposal,
        "denied",
        evaluation.policy.reason_codes,
      );
    }

    if (
      evaluation.policy.decision === "REQUIRE_APPROVAL" ||
      evaluation.policy.decision === "REQUIRE_STRONG_AUTH"
    ) {
      proposal.status = "pending_approval";
      this.#recordEvidence(proposal, "approval.requested", {
        decision: evaluation.policy.decision,
        reason_codes: evaluation.policy.reason_codes,
      });
      return this.#outcome(
        proposal,
        "pending_approval",
        evaluation.policy.reason_codes,
      );
    }

    proposal.status = "ready";
    this.#recordEvidence(proposal, "policy.allowed", {
      decision: "ALLOW",
      reason_codes: evaluation.policy.reason_codes,
    });
    return this.#outcome(proposal, "ready", evaluation.policy.reason_codes);
  }

  async recordApproval(proposalId, input) {
    return this.#withProposalLock(proposalId, async () => {
      const proposal = this.#proposals.get(proposalId);
      if (!proposal) {
        return { status: "rejected", reason_code: "PROPOSAL_NOT_FOUND" };
      }
      if (proposal.status !== "pending_approval") {
        return this.#rejectApproval(proposal, "PROPOSAL_NOT_PENDING_APPROVAL");
      }

      const now = this.#timestamp();
      if (
        !input ||
        !isNonEmptyString(input.approver?.type) ||
        !isNonEmptyString(input.approver?.id) ||
        !isNonEmptyString(input.authorization_source) ||
        !["approved", "denied"].includes(input.decision) ||
        !Number.isSafeInteger(input.expires_at) ||
        input.expires_at <= now ||
        (input.single_use !== undefined && typeof input.single_use !== "boolean") ||
        ![undefined, "normal", "strong"].includes(input.authentication)
      ) {
        return this.#rejectApproval(proposal, "APPROVAL_RECORD_INVALID");
      }

      let approverAuthorized = false;
      try {
        approverAuthorized =
          (await this.#ports.authorizeApprover({
            proposal: publicProposal(proposal),
            approval: clone(input),
          })) === true;
      } catch {
        approverAuthorized = false;
      }
      if (!approverAuthorized) {
        return this.#rejectApproval(proposal, "APPROVER_NOT_AUTHORIZED");
      }

      if (
        input.decision === "approved" &&
        proposal.decision.decision === "REQUIRE_STRONG_AUTH"
      ) {
        let strongAuthenticationVerified = false;
        if (input.authentication === "strong") {
          try {
            strongAuthenticationVerified =
              (await this.#ports.verifyStrongAuthentication({
                proposal: publicProposal(proposal),
                approval: clone(input),
              })) === true;
          } catch {
            strongAuthenticationVerified = false;
          }
        }
        if (!strongAuthenticationVerified) {
          return this.#rejectApproval(proposal, "STRONG_AUTH_REQUIRED");
        }
      }

      const approval = {
        id: this.#newId("approval"),
        proposal_id: proposal.id,
        request_hash: proposal.request_hash,
        approver: clone(input.approver),
        authorization_source: input.authorization_source,
        decision: input.decision,
        decided_at: now,
        expires_at: input.expires_at,
        single_use: input.single_use ?? true,
        authentication: input.authentication ?? "normal",
        replay_state: "unused",
      };
      this.#approvals.set(proposal.id, approval);

      if (approval.decision === "approved") {
        proposal.status = "approved";
        this.#recordEvidence(proposal, "approval.approved", {
          approval_id: approval.id,
          approver: clone(approval.approver),
          authorization_source: approval.authorization_source,
          decision: "approved",
          expires_at: approval.expires_at,
          single_use: approval.single_use,
          authentication: approval.authentication,
        });
      } else {
        proposal.status = "denied";
        this.#recordEvidence(proposal, "approval.rejected", {
          approval_id: approval.id,
          approver: clone(approval.approver),
          authorization_source: approval.authorization_source,
          decision: "denied",
          reason_codes: ["APPROVAL_NOT_APPROVED"],
        });
      }

      return clone({ status: "recorded", approval });
    });
  }

  async execute(proposalId) {
    return this.#withProposalLock(proposalId, async () => {
      const proposal = this.#proposals.get(proposalId);
      if (!proposal) {
        return { status: "denied", reason_code: "PROPOSAL_NOT_FOUND" };
      }

      const approval = this.#approvals.get(proposal.id);
      if (proposal.status === "executed" || proposal.status === "failed") {
        const reason =
          approval?.single_use && approval.replay_state === "used"
            ? "APPROVAL_REPLAYED"
            : "PROPOSAL_ALREADY_EXECUTED";
        return this.#denyExecution(proposal, [reason], "not_attempted");
      }
      if (proposal.status === "denied") {
        const reason =
          approval?.decision === "denied"
            ? "APPROVAL_NOT_APPROVED"
            : "PROPOSAL_NOT_EXECUTABLE";
        return this.#denyExecution(
          proposal,
          [reason],
          "not_attempted",
        );
      }

      const initiallyRequiresApproval =
        proposal.decision?.decision === "REQUIRE_APPROVAL" ||
        proposal.decision?.decision === "REQUIRE_STRONG_AUTH";
      if (initiallyRequiresApproval) {
        if (!approval) {
          return this.#denyExecution(
            proposal,
            ["APPROVAL_REQUIRED"],
            "not_attempted",
          );
        }
        const approvalFailure = this.#validateApproval(proposal, approval);
        if (approvalFailure) {
          return this.#denyExecution(
            proposal,
            [approvalFailure],
            "not_attempted",
          );
        }
      }

      let currentAction;
      try {
        currentAction = await this.#ports.reconstructAction({
          proposal: publicProposal(proposal),
        });
      } catch {
        currentAction = undefined;
      }
      if (!currentAction) {
        return this.#denyExecution(
          proposal,
          ["RECONSTRUCTION_FAILED"],
          "failed",
        );
      }

      let currentDigest;
      try {
        currentDigest = await this.#ports.hashAction(clone(currentAction));
      } catch {
        return this.#denyExecution(
          proposal,
          ["RECONSTRUCTION_FAILED"],
          "failed",
        );
      }
      if (!hashesEqual(currentDigest?.requestHash ?? "", proposal.request_hash)) {
        return this.#denyExecution(
          proposal,
          ["ACTION_HASH_MISMATCH"],
          "mismatched",
        );
      }

      const evaluation = await this.#evaluateMutableGates(
        proposal,
        "execution",
        currentAction,
      );
      if (!evaluation.allowed) {
        return this.#denyExecution(
          proposal,
          evaluation.reason_codes,
          "matched",
        );
      }
      if (evaluation.policy.decision === "DENY") {
        return this.#denyExecution(
          proposal,
          evaluation.policy.reason_codes,
          "matched",
        );
      }
      if (
        evaluation.policy.decision === "REQUIRE_APPROVAL" &&
        !approval
      ) {
        proposal.decision = evaluation.policy;
        return this.#denyExecution(
          proposal,
          ["APPROVAL_REQUIRED"],
          "matched",
        );
      }
      if (
        evaluation.policy.decision === "REQUIRE_STRONG_AUTH" &&
        approval?.authentication !== "strong"
      ) {
        proposal.decision = evaluation.policy;
        return this.#denyExecution(
          proposal,
          ["STRONG_AUTH_REQUIRED"],
          "matched",
        );
      }

      let preconditionsPass = false;
      try {
        preconditionsPass =
          (await this.#ports.checkPreconditions({
            phase: "execution",
            action: clone(currentAction),
            proposal: publicProposal(proposal),
          })) === true;
      } catch {
        preconditionsPass = false;
      }
      if (!preconditionsPass) {
        return this.#denyExecution(
          proposal,
          ["PRECONDITION_FAILED"],
          "matched",
        );
      }

      if (approval?.single_use) {
        approval.replay_state = "used";
      }
      proposal.status = "executing";
      this.#recordEvidence(proposal, "execution.started", {
        approval_id: approval?.id,
        decision: "ALLOW",
      });

      let result;
      try {
        result = await this.#ports.executeAction({
          action: clone(currentAction),
          proposal: publicProposal(proposal),
          approval: clone(approval),
        });
      } catch {
        proposal.status = "failed";
        this.#recordEvidence(proposal, "execution.failed", {
          approval_id: approval?.id,
          result_status: "failed",
          error_code: "EXECUTION_FAILED",
        });
        return this.#outcome(proposal, "failed", ["EXECUTION_FAILED"], {
          reconstruction: "matched",
          replay_state: approval?.replay_state,
        });
      }

      proposal.status = "executed";
      this.#recordEvidence(proposal, "execution.succeeded", {
        approval_id: approval?.id,
        result_status: "succeeded",
      });
      let resultSnapshot;
      try {
        resultSnapshot = clone(result);
      } catch {
        resultSnapshot = undefined;
      }
      return this.#outcome(proposal, "executed", ["EXECUTE_ALLOWED"], {
        reconstruction: "matched",
        replay_state: approval?.replay_state,
        result: resultSnapshot,
      });
    });
  }

  getProposal(proposalId) {
    const proposal = this.#proposals.get(proposalId);
    return proposal ? publicProposal(proposal) : undefined;
  }

  getApproval(proposalId) {
    return clone(this.#approvals.get(proposalId));
  }

  listEvidence() {
    return clone(this.#evidence);
  }

  async #evaluateMutableGates(proposal, phase, action = proposal.action) {
    const context = {
      phase,
      action: clone(action),
      proposal: publicProposal(proposal),
    };

    if (!(await this.#booleanGate("checkApplicationPermission", context))) {
      return { allowed: false, reason_codes: ["APPLICATION_PERMISSION_DENIED"] };
    }
    if (!(await this.#booleanGate("checkDelegation", context))) {
      return { allowed: false, reason_codes: ["DELEGATION_INVALID"] };
    }

    const policy = await this.#policyDecision(context);
    if (policy.decision === "DENY") {
      return { allowed: true, policy };
    }
    if (!(await this.#booleanGate("checkBudget", { ...context, policy }))) {
      return { allowed: false, reason_codes: ["BUDGET_EXHAUSTED"], policy };
    }

    return { allowed: true, policy };
  }

  async #booleanGate(name, context) {
    try {
      return (await this.#ports[name](context)) === true;
    } catch {
      return false;
    }
  }

  async #policyDecision(context) {
    let value;
    try {
      value = await this.#ports.evaluatePolicy(context);
    } catch {
      value = undefined;
    }
    const decision = value?.decision;
    if (!POLICY_DECISIONS.has(decision)) {
      return this.#invalidPolicy(context.action, "POLICY_INVALID");
    }

    const policyId = value.policy_id ?? context.action.policy?.id;
    const policyVersion = value.policy_version ?? context.action.policy?.version;
    if (
      policyId !== context.action.policy?.id ||
      policyVersion !== context.action.policy?.version
    ) {
      return this.#invalidPolicy(context.action, "POLICY_VERSION_MISMATCH");
    }

    const fallback =
      decision === "DENY"
        ? ["POLICY_DENIED"]
        : decision === "REQUIRE_APPROVAL"
          ? ["APPROVAL_REQUIRED"]
          : decision === "REQUIRE_STRONG_AUTH"
            ? ["STRONG_AUTH_REQUIRED"]
            : [];
    return {
      decision,
      reason_codes: stringList(value.reason_codes, fallback),
      policy_id: policyId,
      policy_version: policyVersion,
      matched_rules: stringList(value.matched_rules),
      required_controls: stringList(value.required_controls),
    };
  }

  #invalidPolicy(action, reasonCode) {
    return {
      decision: "DENY",
      reason_codes: [reasonCode],
      policy_id: action.policy?.id,
      policy_version: action.policy?.version,
      matched_rules: [],
      required_controls: [],
    };
  }

  #validateApproval(proposal, approval) {
    if (
      !approval ||
      !isNonEmptyString(approval.id) ||
      approval.proposal_id !== proposal.id ||
      !hashesEqual(approval.request_hash, proposal.request_hash) ||
      !isNonEmptyString(approval.approver?.type) ||
      !isNonEmptyString(approval.approver?.id) ||
      !isNonEmptyString(approval.authorization_source) ||
      !Number.isSafeInteger(approval.decided_at) ||
      !Number.isSafeInteger(approval.expires_at) ||
      approval.decided_at >= approval.expires_at ||
      typeof approval.single_use !== "boolean" ||
      !["unused", "used"].includes(approval.replay_state)
    ) {
      return "APPROVAL_BINDING_INVALID";
    }
    const now = this.#timestamp();
    if (approval.decision !== "approved" || now < approval.decided_at) {
      return "APPROVAL_NOT_APPROVED";
    }
    if (now >= approval.expires_at) {
      return "APPROVAL_EXPIRED";
    }
    if (approval.single_use && approval.replay_state === "used") {
      return "APPROVAL_REPLAYED";
    }
    if (
      proposal.decision.decision === "REQUIRE_STRONG_AUTH" &&
      approval.authentication !== "strong"
    ) {
      return "STRONG_AUTH_REQUIRED";
    }
    return undefined;
  }

  #rejectApproval(proposal, reasonCode) {
    this.#recordEvidence(proposal, "approval.rejected", {
      decision: "rejected",
      reason_codes: [reasonCode],
    });
    return { status: "rejected", reason_code: reasonCode };
  }

  #denyExecution(proposal, reasonCodes, reconstruction) {
    const reasonCode = reasonCodes[0];
    const eventType =
      reasonCode === "APPROVAL_EXPIRED"
        ? "approval.expired"
        : reasonCode.startsWith("APPROVAL_") || reasonCode === "STRONG_AUTH_REQUIRED"
          ? "approval.rejected"
          : "execution.cancelled";
    this.#recordEvidence(proposal, eventType, {
      approval_id: this.#approvals.get(proposal.id)?.id,
      decision: "DENY",
      reason_codes: reasonCodes,
    });
    if (
      reasonCode === "APPROVAL_EXPIRED" ||
      reasonCode === "APPROVAL_REQUIRED" ||
      reasonCode === "STRONG_AUTH_REQUIRED"
    ) {
      proposal.status = "pending_approval";
    }
    return this.#outcome(proposal, "denied", reasonCodes, { reconstruction });
  }

  #outcome(proposal, status, reasonCodes, extra = {}) {
    return clone({
      status,
      reason_code: reasonCodes[0],
      reason_codes: reasonCodes,
      proposal_id: proposal.id,
      request_hash: proposal.request_hash,
      ...extra,
    });
  }

  #recordEvidence(proposal, eventType, extra = {}) {
    const action = proposal.action;
    const event = {
      event_id: this.#newId("event"),
      event_type: eventType,
      occurred_at: this.#timestamp(),
      request_id: proposal.id,
      application: clone(action.application),
      application_principal: clone(action.application_principal),
      protocol: "reference",
      capability: action.capability,
      risk_class: action.impact?.risk_class,
      data_classes: clone(action.impact?.data_classes ?? []),
      request_hash: proposal.request_hash,
      policy_id: action.policy?.id,
      policy_version: action.policy?.version,
      delegation_id: action.delegation?.id,
      external_transmission: Boolean(action.transmission),
      recipient: action.transmission?.recipient,
      retention_class: "reference-ephemeral",
      schema_version: "draft-0.1",
      agent_identity: clone(action.actors?.agent),
      client_identity: clone(action.actors?.client),
      ...clone(extra),
    };
    for (const [key, value] of Object.entries(event)) {
      if (value === undefined) {
        delete event[key];
      }
    }
    this.#evidence.push(event);
  }

  #timestamp() {
    const value = this.#now();
    if (!Number.isSafeInteger(value)) {
      throw new TypeError("now port must return an integer Unix timestamp");
    }
    return value;
  }

  #newId(kind) {
    const value = this.#idFactory(kind);
    if (!isNonEmptyString(value)) {
      throw new TypeError("idFactory must return a non-empty string");
    }
    if (this.#ids.has(value)) {
      throw new TypeError("idFactory must return a unique string");
    }
    this.#ids.add(value);
    return value;
  }

  async #withProposalLock(proposalId, operation) {
    const previous = this.#locks.get(proposalId) ?? Promise.resolve();
    let release;
    const gate = new Promise((resolve) => {
      release = resolve;
    });
    const tail = previous.then(() => gate);
    this.#locks.set(proposalId, tail);
    await previous;
    try {
      return await operation();
    } finally {
      release();
      if (this.#locks.get(proposalId) === tail) {
        this.#locks.delete(proposalId);
      }
    }
  }
}
