#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUNTIME_DIR="${SCRIPT_DIR}/runtime"
WORDPRESS_DIR="${RUNTIME_DIR}/wordpress"
PID_FILE="${RUNTIME_DIR}/server.pid"
LOG_FILE="${RUNTIME_DIR}/server.log"
URL="http://127.0.0.1:8081"
PHP_BIN_FILE="${RUNTIME_DIR}/php-bin"

if [[ ! -f "${WORDPRESS_DIR}/wp-config.php" || ! -f "${PHP_BIN_FILE}" ]]; then
	echo "Run npm run e2e:wordpress:setup first." >&2
	exit 1
fi

PHP_BIN="$(cat "${PHP_BIN_FILE}")"
if [[ ! -x "${PHP_BIN}" ]]; then
	echo "Configured E2E PHP is not executable: ${PHP_BIN}" >&2
	exit 1
fi

if [[ -f "${PID_FILE}" ]]; then
	pid="$(cat "${PID_FILE}")"
	if kill -0 "${pid}" 2>/dev/null; then
		command_line="$(ps -p "${pid}" -o command=)"
		if [[ "${command_line}" == *"-S 127.0.0.1:8081"* && "${command_line}" == *"${SCRIPT_DIR}/router.php"* ]]; then
			echo "WordPress E2E server is already running at ${URL} (PID ${pid})."
			exit 0
		fi

		echo "PID file points to a different live process: ${pid}" >&2
		exit 1
	fi
	rm "${PID_FILE}"
fi

if "${PHP_BIN}" -r '$socket = @fsockopen("127.0.0.1", 8081, $errno, $error, 0.2); exit(is_resource($socket) ? 0 : 1);'; then
	echo "Port 127.0.0.1:8081 is already in use and is not owned by this PID file." >&2
	exit 1
fi

nohup "${PHP_BIN}" -S 127.0.0.1:8081 -t "${WORDPRESS_DIR}" "${SCRIPT_DIR}/router.php" >"${LOG_FILE}" 2>&1 &
pid="$!"
printf '%s\n' "${pid}" >"${PID_FILE}"

for _ in {1..40}; do
	if ! kill -0 "${pid}" 2>/dev/null; then
		echo "Server exited before becoming ready. See ${LOG_FILE}" >&2
		rm -f "${PID_FILE}"
		exit 1
	fi
	if curl --fail --silent "${URL}/wp-json/" >/dev/null; then
		echo "WordPress E2E server is running at ${URL} (PID ${pid})."
		exit 0
	fi
	sleep 0.25
done

echo "Server did not become ready. See ${LOG_FILE}" >&2
kill "${pid}" 2>/dev/null || true
rm -f "${PID_FILE}"
exit 1
