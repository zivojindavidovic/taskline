#!/usr/bin/env bash
# =============================================================================
# Build the Taskline image (frontend + vendor baked in) and push to a registry.
#
#   ./dist/build-and-push.sh         (runs from anywhere; resolves its own paths)
#
# Overrides:
#   IMAGE=youruser/taskline TAG=1.0.0 ./dist/build-and-push.sh
#   PLATFORM=linux/amd64,linux/arm64 ./dist/build-and-push.sh   (buildx only)
#
# This script lives in dist/, but the Docker build CONTEXT is the project root
# (the image bakes in the whole Laravel app). It builds with `-f dist/Dockerfile`
# and the project root as context.
#
# Builds multi-arch (linux/amd64 + linux/arm64) by default so the image runs
# on either server architecture. This script:
#   * uses `docker buildx` to build all platforms when available, else
#   * does a single-arch native build when the daemon matches the (single)
#     requested platform, else
#   * refuses, because the resulting image would NOT run on the server.
#
# `docker login` must already be done.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DOCKERFILE="$SCRIPT_DIR/Dockerfile"

IMAGE="${IMAGE:-zivojindavidovic/taskline}"
TAG="${TAG:-latest}"
PLATFORM="${PLATFORM:-linux/amd64,linux/arm64}"
GIT_SHA="$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo manual)"

if docker buildx version >/dev/null 2>&1; then
    echo ">> buildx available — building ${IMAGE}:${TAG} (+ :${GIT_SHA}) for ${PLATFORM} and pushing"
    BUILDER="taskline-builder"
    docker buildx inspect "$BUILDER" >/dev/null 2>&1 \
        || docker buildx create --name "$BUILDER" --driver docker-container --use >/dev/null
    docker buildx use "$BUILDER"
    docker buildx build \
        --platform "${PLATFORM}" \
        --file "${DOCKERFILE}" \
        --tag "${IMAGE}:${TAG}" \
        --tag "${IMAGE}:${GIT_SHA}" \
        --push \
        "${REPO_ROOT}"
    echo ">> Pushed ${IMAGE}:${TAG} and ${IMAGE}:${GIT_SHA}"
    exit 0
fi

ARCH="$(docker info --format '{{.Architecture}}' 2>/dev/null || echo unknown)"
case "$ARCH" in
    x86_64 | amd64 | aarch64 | arm64)
        echo ">> No buildx — native single-arch (${ARCH}) build of ${IMAGE}:${TAG} (+ :${GIT_SHA})"
        echo ">> NOTE: this pushes ONLY ${ARCH}; servers on the other arch cannot pull it."
        docker build --file "${DOCKERFILE}" --tag "${IMAGE}:${TAG}" --tag "${IMAGE}:${GIT_SHA}" "${REPO_ROOT}"
        docker push "${IMAGE}:${TAG}"
        docker push "${IMAGE}:${GIT_SHA}"
        echo ">> Pushed ${IMAGE}:${TAG} and ${IMAGE}:${GIT_SHA}"
        ;;
    *)
        cat >&2 <<EOF

ERROR: docker buildx is not available and this daemon arch is '${ARCH}'.
A multi-arch build (${PLATFORM}) requires buildx.

Enable buildx on this machine:
    brew install docker-buildx
    mkdir -p ~/.docker/cli-plugins
    ln -sf "\$(brew --prefix)/bin/docker-buildx" ~/.docker/cli-plugins/docker-buildx
then re-run this script.
EOF
        exit 1
        ;;
esac
