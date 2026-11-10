#!/usr/bin/env bash
# Turn the downloaded photos into catalogue images: a 1200x1200 square JPG of at most 250 KB.
# Usage: showcase/photos/process.sh <raw-dir> <out-dir>
set -euo pipefail

raw=${1:?raw directory}
out=${2:?output directory}
max_bytes=$((250 * 1024))

# Where to anchor the square crop when the centre would cut the product off.
gravity_for() {
    case "$1" in
        olive-oil) echo north ;;
        *) echo center ;;
    esac
}

mkdir -p "$out"
for src in "$raw"/*.jpg; do
    slug=$(basename "$src" .jpg)
    dest="$out/$slug.jpg"
    for quality in 82 78 74 70; do
        magick "$src" -auto-orient -gravity "$(gravity_for "$slug")" \
            -resize '1200x1200^' -extent 1200x1200 \
            -strip -sampling-factor 4:2:0 -interlace JPEG -quality "$quality" "$dest"
        [ "$(stat -c %s "$dest")" -le "$max_bytes" ] && break
    done
    size=$(stat -c %s "$dest")
    if [ "$size" -gt "$max_bytes" ]; then
        echo "$slug is still $size bytes at quality 70" >&2
        exit 1
    fi
    echo "$slug $(magick identify -format '%wx%h' "$dest") $((size / 1024)) KB"
done
