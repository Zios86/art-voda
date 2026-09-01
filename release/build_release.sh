#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="${1:-v18-rc1}"
date_label="${2:-2026-09-01}"
safe_version="${version//[^a-zA-Z0-9._-]/-}"
output_dir="$project_root/dist"
archive="$output_dir/kioskvody_timeweb_${date_label}_${safe_version}.zip"
manifest="$project_root/release/RELEASE_MANIFEST.txt"
stage="$(mktemp -d)"
trap 'rm -rf -- "$stage"' EXIT

command -v zip >/dev/null || { echo "Ошибка: команда zip не найдена." >&2; exit 1; }
command -v sha256sum >/dev/null || { echo "Ошибка: команда sha256sum не найдена." >&2; exit 1; }
[[ -f "$manifest" ]] || { echo "Ошибка: нет RELEASE_MANIFEST.txt" >&2; exit 1; }

while IFS= read -r relative || [[ -n "$relative" ]]; do
    [[ -z "$relative" ]] && continue
    [[ "$relative" != /* && "$relative" != *".."* ]] || { echo "Опасный путь в manifest: $relative" >&2; exit 1; }
    source_path="$project_root/$relative"
    [[ -f "$source_path" ]] || { echo "Нет файла из manifest: $relative" >&2; exit 1; }
    mkdir -p "$stage/$(dirname "$relative")"
    cp -p "$source_path" "$stage/$relative"
done < "$manifest"

for forbidden in private/config.php public_html/img/index.php .git; do
    [[ ! -e "$stage/$forbidden" ]] || { echo "Запрещённый файл попал в релиз: $forbidden" >&2; exit 1; }
done
if find "$stage" -type f \( -name '*.log' -o -name '*.zip' -o -name '*.bak' \) -print -quit | grep -q .; then
    echo "В релиз попал запрещённый служебный файл." >&2
    exit 1
fi

(cd "$stage" && find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS)
mkdir -p "$output_dir"
rm -f -- "$archive" "$archive.sha256"
(cd "$stage" && zip -q -9 -r "$archive" .)
(cd "$output_dir" && sha256sum "$(basename "$archive")" > "$(basename "$archive").sha256")

test_dir="$(mktemp -d)"
unzip -q -t "$archive"
unzip -q "$archive" -d "$test_dir"
diff -u <(cd "$stage" && find . -type f | sort) <(cd "$test_dir" && find . -type f | sort)
rm -rf -- "$test_dir"

echo "$archive"
echo "$archive.sha256"
