#!/usr/bin/env node

import { spawnSync } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");

function run(command, args) {
  const result = spawnSync(command, args, {
    cwd: root,
    encoding: "utf8",
    maxBuffer: 16 * 1024 * 1024,
  });
  if (result.status !== 0) {
    process.stderr.write(result.stderr);
    throw new Error(`${command} exited with status ${result.status}`);
  }
  return JSON.parse(result.stdout);
}

const javascript = run(process.execPath, [
  "tools/conformance/approval-lifecycle-javascript.mjs",
  "--emit",
]);
const php = run("php", [
  "tools/conformance/approval-lifecycle-php.php",
  "--emit",
]);
const javascriptIds = Object.keys(javascript).sort();
const phpIds = Object.keys(php).sort();

if (JSON.stringify(javascriptIds) !== JSON.stringify(phpIds)) {
  throw new Error("runtime lifecycle case lists differ");
}

let attempts = 0;
for (const id of javascriptIds) {
  if (JSON.stringify(javascript[id]) !== JSON.stringify(php[id])) {
    throw new Error(`lifecycle outcomes differ for ${id}`);
  }
  attempts += javascript[id].length;
}

console.log(
  `Lifecycle cross-runtime: ${javascriptIds.length} cases, ${attempts} outcome matches`,
);
