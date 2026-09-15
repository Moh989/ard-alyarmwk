#!/usr/bin/env bash
set -e
ARD_PREVIEW_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ARD_PREVIEW_DIR"
exec ./bin/preview.sh
