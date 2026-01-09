#!/usr/bin/env bash
set -euo pipefail

# Script para vendorizar isomorphic-git e lightning-fs em assets locais
# Compatível com docroot "/" e "/public"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/src/codeigniter-app"
ASSET_BASES=(
  "$APP_DIR/assets/vendor"
  "$APP_DIR/public/assets/vendor"
)

ISO_VER="1.25.7"
LFS_VER=""
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

log() { echo -e "[setup-git-assets] $*"; }

ensure_dirs() {
  for base in "${ASSET_BASES[@]}"; do
    mkdir -p "$base/isomorphic-git" "$base/lightning-fs"
  done
}

download_and_extract() {
  log "Baixando isomorphic-git@$ISO_VER"
  curl -fsSL "https://registry.npmjs.org/isomorphic-git/-/isomorphic-git-${ISO_VER}.tgz" -o "$TMP_DIR/isogit.tgz"
  mkdir -p "$TMP_DIR/isogit"
  tar -xzf "$TMP_DIR/isogit.tgz" -C "$TMP_DIR/isogit"

  log "Resolvendo tarball do lightning-fs (várias tentativas)"
  mkdir -p "$TMP_DIR/lfs"
  LFS_TARBALL=""
  # Pacotes e versões candidatos
  LFS_PKGS=("@isomorphic-git/lightning-fs" "lightning-fs")
  LFS_VERS=("4.6.3" "4.6.2" "4.6.1" "4.6.0" "4.5.0" "4.4.0" "4.3.0" "4.2.0" "4.1.0" "4.0.0" "3.4.0")
  for PKG in "${LFS_PKGS[@]}"; do
    for VER in "${LFS_VERS[@]}"; do
      if [[ "$PKG" == @* ]]; then
        # escopo precisa ser codificado
        URL="https://registry.npmjs.org/@isomorphic-git%2Flightning-fs/-/lightning-fs-${VER}.tgz"
      else
        URL="https://registry.npmjs.org/lightning-fs/-/lightning-fs-${VER}.tgz"
      fi
      log "Tentando ${PKG}@${VER} -> $URL"
      if curl -fsSL "$URL" -o "$TMP_DIR/lfs.tgz"; then
        LFS_VER="$VER"
        LFS_TARBALL="$URL"
        break 2
      fi
    done
  done
  if [[ -z "$LFS_TARBALL" ]]; then
    log "ERRO: Não foi possível baixar lightning-fs em versões conhecidas"
    exit 1
  fi
  tar -xzf "$TMP_DIR/lfs.tgz" -C "$TMP_DIR/lfs"
}

select_file() {
  # $1 = base path, $2.. = candidate paths relative to base
  local base="$1"; shift
  local cand
  for cand in "$@"; do
    if [[ -f "$base/$cand" ]]; then
      echo "$base/$cand"
      return 0
    fi
  done
  return 1
}

copy_files() {
  # UMD (preferível), mas se não houver, usa ESM
  local ISO_UMD
  ISO_UMD="$(select_file "$TMP_DIR/isogit/package" \
    "dist/bundle.umd.min.js" \
    "dist/bundle.umd.js" \
    "dist/bundle.umd.cjs" || true)"

  local LFS_UMD
  LFS_UMD="$(select_file "$TMP_DIR/lfs/package" \
    "dist/lightning-fs.min.js" \
    "dist/lightning-fs.umd.min.js" \
    "dist/lightning-fs.umd.js" \
    "dist/lightning-fs.js" || true)"

  # ESM (best-effort)
  local ISO_INDEX
  ISO_INDEX="$(select_file "$TMP_DIR/isogit/package" \
    "index.js" \
    "dist/index.js" \
    "dist/for-browser.js" \
    "dist/for-firebase.js" || true)"

  local ISO_HTTP
  ISO_HTTP="$(select_file "$TMP_DIR/isogit/package" \
    "http/web/index.js" || true)"

  local LFS_ESM
  LFS_ESM="$(select_file "$TMP_DIR/lfs/package" \
    "dist/lightning-fs.js" \
    "index.js" || true)"

  # Validar que temos pelo menos UMA opção por pacote (UMD ou ESM)
  if [[ -z "${ISO_UMD:-}" && -z "${ISO_INDEX:-}" ]]; then
    log "ERRO: Não encontrei isomorphic-git (nem UMD nem ESM) no pacote"
    exit 1
  fi
  if [[ -z "${LFS_UMD:-}" && -z "${LFS_ESM:-}" ]]; then
    log "ERRO: Não encontrei lightning-fs (nem UMD nem ESM) no pacote"
    exit 1
  fi

  for base in "${ASSET_BASES[@]}"; do
    log "Copiando para $base"
    mkdir -p "$base/isomorphic-git" "$base/lightning-fs"

    # Copiar UMD quando existir
    if [[ -n "${ISO_UMD:-}" && -f "$ISO_UMD" ]]; then
      cp "$ISO_UMD" "$base/isomorphic-git/bundle.umd.min.js"
    fi
    if [[ -n "${LFS_UMD:-}" && -f "$LFS_UMD" ]]; then
      cp "$LFS_UMD" "$base/lightning-fs/lightning-fs.min.js"
    fi

    # Copiar ESM quando existir
    if [[ -n "${ISO_INDEX:-}" && -f "$ISO_INDEX" ]]; then
      cp "$ISO_INDEX" "$base/isomorphic-git/index.js"
    fi
    if [[ -n "${ISO_HTTP:-}" && -f "$ISO_HTTP" ]]; then
      cp "$ISO_HTTP" "$base/isomorphic-git/http-web.js"
    fi
    if [[ -n "${LFS_ESM:-}" && -f "$LFS_ESM" ]]; then
      cp "$LFS_ESM" "$base/lightning-fs/index.js"
    fi
  done
}

main() {
  log "ROOT_DIR=$ROOT_DIR"
  log "APP_DIR=$APP_DIR"
  ensure_dirs
  download_and_extract
  copy_files
  log "Concluído. Arquivos copiados para:"
  for base in "${ASSET_BASES[@]}"; do
    echo "  - $base/isomorphic-git/bundle.umd.min.js"
    echo "  - $base/lightning-fs/lightning-fs.min.js"
    [[ -f "$base/isomorphic-git/index.js" ]] && echo "  - $base/isomorphic-git/index.js"
    [[ -f "$base/isomorphic-git/http-web.js" ]] && echo "  - $base/isomorphic-git/http-web.js"
    [[ -f "$base/lightning-fs/index.js" ]] && echo "  - $base/lightning-fs/index.js"
  done
  log "Recarregue o navegador (Ctrl+F5). Você deve ver: '✓ Git UMD via assets locais carregado'"
}

main "$@"
