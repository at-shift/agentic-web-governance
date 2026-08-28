#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_FILE="${SCRIPT_DIR}/runtime/server.pid"

if [[ ! -f "${PID_FILE}" ]]; then
	echo "WordPress E2E server is not running."
	exit 0
fi

pid="$(cat "${PID_FILE}")"
if ! kill -0 "${pid}" 2>/dev/null; then
	rm "${PID_FILE}"
	echo "Removed a stale WordPress E2E PID file."
	exit 0
fi

command_line="$(ps -p "${pid}" -o command=)"
if [[ "${command_line}" != *"-S 127.0.0.1:8081"* || "${command_line}" != *"${SCRIPT_DIR}/router.php"* ]]; then
	echo "Refusing to stop PID ${pid}; it is not the expected E2E server." >&2
	exit 1
fi

kill "${pid}"
for _ in {1..20}; do
	if ! kill -0 "${pid}" 2>/dev/null; then
		rm "${PID_FILE}"
		echo "WordPress E2E server stopped."
		exit 0
	fi
	sleep 0.1
done

echo "WordPress E2E server did not stop cleanly (PID ${pid})." >&2
exit 1
