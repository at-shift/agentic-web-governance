#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORDPRESS_DIR="${SCRIPT_DIR}/runtime/wordpress"
DOWNLOAD_DIR="${SCRIPT_DIR}/runtime/downloads"
WORDPRESS_ARCHIVE="${DOWNLOAD_DIR}/wordpress-7.1.tar.gz"
MCP_VERSION="0.7.0"
MCP_ARCHIVE="${DOWNLOAD_DIR}/mcp-adapter-${MCP_VERSION}.zip"

WORDPRESS_URL="https://wordpress.org/wordpress-7.1.tar.gz"
WORDPRESS_SHA256="05a5f89138f632b7329f1202f2a0553c5f7fe4daf8e4b9ca7ebae9b9466b9e86"
MCP_URL="https://github.com/WordPress/mcp-adapter/releases/download/v${MCP_VERSION}/mcp-adapter.zip"
MCP_SHA256="9168c18dbd018428ff14ee28e7018aa0399d4b8819b7c98b406f6610731c3a79"
PHP_BIN="${AWG_E2E_PHP:-php}"

if ! PHP_BIN="$(command -v "${PHP_BIN}")"; then
	echo "PHP executable not found: ${AWG_E2E_PHP:-php}" >&2
	exit 1
fi

if ! "${PHP_BIN}" -r 'exit(extension_loaded("curl") && extension_loaded("mysqli") && extension_loaded("pdo_mysql") ? 0 : 1);'; then
	echo "E2E PHP must provide curl, mysqli, and pdo_mysql: ${PHP_BIN}" >&2
	echo "Set AWG_E2E_PHP to a PHP executable with those extensions." >&2
	exit 1
fi

download_if_missing() {
	local url="$1"
	local destination="$2"

	if [[ -f "${destination}" ]]; then
		return
	fi

	echo "Downloading $(basename "${destination}")"
	curl --fail --location --silent --show-error "${url}" --output "${destination}.part"
	mv "${destination}.part" "${destination}"
}

verify_sha256() {
	local file="$1"
	local expected="$2"
	local actual

	actual="$("${PHP_BIN}" -r 'echo hash_file("sha256", $argv[1]);' "${file}")"
	if [[ "${actual}" != "${expected}" ]]; then
		echo "SHA-256 mismatch for ${file}" >&2
		rm "${file}"
		exit 1
	fi
}

link_owned_fixture() {
	local source="$1"
	local destination="$2"

	if [[ -e "${destination}" && ! -L "${destination}" ]]; then
		echo "Refusing to replace non-symlink path: ${destination}" >&2
		exit 1
	fi

	ln -sfn "${source}" "${destination}"
}

mkdir -p "${DOWNLOAD_DIR}"
printf '%s\n' "${PHP_BIN}" >"${SCRIPT_DIR}/runtime/php-bin"

download_if_missing "${WORDPRESS_URL}" "${WORDPRESS_ARCHIVE}"
download_if_missing "${MCP_URL}" "${MCP_ARCHIVE}"
verify_sha256 "${WORDPRESS_ARCHIVE}" "${WORDPRESS_SHA256}"
verify_sha256 "${MCP_ARCHIVE}" "${MCP_SHA256}"

if [[ ! -f "${WORDPRESS_DIR}/wp-includes/version.php" ]]; then
	echo "Extracting WordPress 7.1"
	rm -rf "${SCRIPT_DIR}/runtime/wordpress-extract"
	mkdir -p "${SCRIPT_DIR}/runtime/wordpress-extract"
	tar -xzf "${WORDPRESS_ARCHIVE}" -C "${SCRIPT_DIR}/runtime/wordpress-extract"
	mv "${SCRIPT_DIR}/runtime/wordpress-extract/wordpress" "${WORDPRESS_DIR}"
	rmdir "${SCRIPT_DIR}/runtime/wordpress-extract"
fi

MCP_PLUGIN_FILE="${WORDPRESS_DIR}/wp-content/plugins/mcp-adapter/mcp-adapter.php"
if [[ ! -f "${MCP_PLUGIN_FILE}" ]] ||
	! grep -Eq "^[[:space:]*#/]*Version:[[:space:]]*${MCP_VERSION}([[:space:]]|$)" "${MCP_PLUGIN_FILE}" ||
	[[ ! -f "${WORDPRESS_DIR}/wp-content/plugins/mcp-adapter/vendor/autoload.php" ]]; then
	# A cached older plugin is not compatible evidence. Replace it before the
	# acceptance run so the asserted version and exercised code are identical.
	echo "Extracting MCP Adapter ${MCP_VERSION}"
	rm -rf "${WORDPRESS_DIR}/wp-content/plugins/mcp-adapter"
	unzip -q "${MCP_ARCHIVE}" -d "${WORDPRESS_DIR}/wp-content/plugins"
fi

mkdir -p "${WORDPRESS_DIR}/wp-content/mu-plugins"
link_owned_fixture \
	"${SCRIPT_DIR}/../plugin" \
	"${WORDPRESS_DIR}/wp-content/plugins/agentic-web-governance-reference"
link_owned_fixture \
	"${SCRIPT_DIR}/fixtures/agentic-web-governance-e2e.php" \
	"${WORDPRESS_DIR}/wp-content/mu-plugins/agentic-web-governance-e2e.php"

"${PHP_BIN}" "${SCRIPT_DIR}/install.php"

# WordPress requirement/database failures can render an HTML error and exit 0.
# Credentials are written only after installation is genuinely complete.
if [[ ! -f "${SCRIPT_DIR}/runtime/credentials.json" ]]; then
	echo "E2E installation did not complete; credentials.json is missing." >&2
	exit 1
fi

echo "Setup complete. Start with: npm run e2e:wordpress:start"
