// SPDX-License-Identifier: GPL-2.0-or-later

import { registerGovernedWebMcpTool } from "./governed-tool-adapter.mjs";

export function siteSummaryAction({
  input,
  context,
  applicationPrincipal,
  applicationInstanceId,
}) {
  return {
    version: 1,
    application: {
      type: "web-application",
      instance_id: applicationInstanceId,
    },
    application_principal: structuredClone(applicationPrincipal),
    capability: "site/summary:read",
    arguments: {
      fields: input.fields ?? ["name", "description", "url"],
    },
    resource: {
      type: "web-origin",
      id: context.owner_origin,
    },
    impact: {
      risk_class: "read",
      reversibility: "reversible",
      data_classes: ["PUBLIC"],
    },
    policy: {
      id: "web-reference-read-policy",
      version: "1",
    },
  };
}

export async function registerSiteSummaryTool({
  document,
  kernel,
  applicationPrincipal,
  applicationInstanceId,
  registrationSignal,
}) {
  if (
    !applicationPrincipal ||
    typeof applicationPrincipal.type !== "string" ||
    typeof applicationPrincipal.id !== "string"
  ) {
    throw new TypeError(
      "applicationPrincipal must come from the web application's authenticated session",
    );
  }
  if (
    typeof applicationInstanceId !== "string" ||
    applicationInstanceId.length === 0
  ) {
    throw new TypeError(
      "applicationInstanceId must be a stable application identifier",
    );
  }

  return registerGovernedWebMcpTool({
    document,
    kernel,
    name: "agentic_web_governance_site_summary",
    title: "Site summary",
    description: "Returns the public summary of the current web application.",
    inputSchema: {
      type: "object",
      properties: {
        fields: {
          type: "array",
          items: { enum: ["name", "description", "url"] },
          uniqueItems: true,
          maxItems: 3,
        },
      },
      additionalProperties: false,
    },
    annotations: {
      readOnlyHint: true,
      untrustedContentHint: false,
      consequentialHint: false,
    },
    validateInput(input) {
      if (Object.keys(input).some((key) => key !== "fields")) {
        return ["INPUT_PROPERTY_UNSUPPORTED"];
      }
      if (
        input.fields !== undefined &&
        (!Array.isArray(input.fields) ||
          input.fields.length > 3 ||
          input.fields.some(
            (field) => !["name", "description", "url"].includes(field),
          ) ||
          new Set(input.fields).size !== input.fields.length)
      ) {
        return ["INPUT_FIELDS_INVALID"];
      }
      return true;
    },
    buildAction({ input, context }) {
      return siteSummaryAction({
        input,
        context,
        applicationPrincipal,
        applicationInstanceId,
      });
    },
    registrationSignal,
  });
}
