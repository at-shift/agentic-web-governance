#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN_FILE="${SCRIPT_DIR}/runtime/php-bin"

if [[ ! -f "${PHP_BIN_FILE}" ]]; then
	echo "Run npm run e2e:wordpress:setup first." >&2
	exit 1
fi

PHP_BIN="$(cat "${PHP_BIN_FILE}")"
if [[ ! -x "${PHP_BIN}" ]]; then
	echo "Configured E2E PHP is not executable: ${PHP_BIN}" >&2
	exit 1
fi

exec "${PHP_BIN}" "${SCRIPT_DIR}/verify.php"
