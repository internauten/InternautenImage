#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_FILE="${ROOT_DIR}/internautenimage/internautenimage.php"
REMOTE="origin"

if [[ ! -f "${MODULE_FILE}" ]]; then
  echo "Module file not found: ${MODULE_FILE}" >&2
  exit 1
fi

# Extracts version from a line like: $this->version = '0.0.1';
version_line="$(grep -F '$this->version' "${MODULE_FILE}" | head -n 1 || true)"
if [[ -z "${version_line}" ]]; then
  echo "Could not find module version in ${MODULE_FILE}" >&2
  exit 1
fi

version="$(echo "${version_line}" | sed -E "s/.*'([^']+)'.*/\1/")"
if [[ ! "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid version format in module: ${version}" >&2
  echo "Expected semantic version like 1.2.3" >&2
  exit 1
fi

tag="v${version}"

if git rev-parse "${tag}" >/dev/null 2>&1; then
  echo "Local tag ${tag} already exists."
else
  git tag "${tag}"
  echo "Created local tag: ${tag}"
fi

if git ls-remote --exit-code --tags "${REMOTE}" "refs/tags/${tag}" >/dev/null 2>&1; then
  echo "Remote tag ${tag} already exists on ${REMOTE}. Nothing to push."
  exit 0
fi

git push "${REMOTE}" "${tag}"
echo "Pushed tag ${tag} to ${REMOTE}."
