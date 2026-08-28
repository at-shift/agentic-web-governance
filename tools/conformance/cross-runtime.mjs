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
  "tools/conformance/javascript.mjs",
  "--emit",
]);
const php = run("php", ["tools/conformance/php.php", "--emit"]);
const javascriptIds = Object.keys(javascript).sort();
const phpIds = Object.keys(php).sort();

if (JSON.stringify(javascriptIds) !== JSON.stringify(phpIds)) {
  throw new Error("runtime case lists differ");
}

for (const id of javascriptIds) {
  if (
    javascript[id].expected_canonical_hex !== php[id].expected_canonical_hex
  ) {
    throw new Error(`canonical bytes differ for ${id}`);
  }
  if (javascript[id].expected_hash !== php[id].expected_hash) {
    throw new Error(`request hash differs for ${id}`);
  }
}

if (process.argv.includes("--emit-hashes")) {
  console.log(
    JSON.stringify(
      Object.fromEntries(
        javascriptIds.map((id) => [id, javascript[id].expected_hash]),
      ),
      null,
      2,
    ),
  );
} else {
  console.log(`Cross-runtime: ${javascriptIds.length} canonical byte matches`);
}
