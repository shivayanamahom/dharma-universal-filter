#!/usr/bin/env bash
# Собирает устанавливаемые ZIP-архивы Joomla в папку dist/ (macOS, Linux, WSL).
# Пути внутри архивов записываются через "/", как требуется установщику Joomla.
#
# Запуск из любого места:
#   build/build-package.sh            # версия берётся из package/pkg_dharma_universal_filter.xml
#   build/build-package.sh 0.2.1      # или указывается явно (должна совпадать с именами файлов в манифесте пакета)
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION="${1:-$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' package/pkg_dharma_universal_filter.xml | head -1)}"
[ -n "$VERSION" ] || { echo "Не удалось определить версию" >&2; exit 1; }

DIST="dist"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

rm -rf "$DIST"
mkdir -p "$DIST"
DIST_ABS="$(cd "$DIST" && pwd)"

# zip_dir <каталог-источник> <имя-архива>: содержимое каталога попадает в корень архива.
zip_dir() {
  (cd "$1" && zip -X -q -r "$DIST_ABS/$2" . -x '*.DS_Store' -x '__MACOSX/*')
}

LIB="lib_dharma_universal_filter_${VERSION}.zip"
MOD="mod_dharma_universal_filter_${VERSION}.zip"
SYS="plg_system_dharma_universal_filter_${VERSION}.zip"
TASK="plg_task_dharma_universal_filter_${VERSION}.zip"
PKG="pkg_dharma_universal_filter_${VERSION}.zip"

zip_dir src/libraries/dharma_universal_filter       "$LIB"
zip_dir src/modules/mod_dharma_universal_filter     "$MOD"
zip_dir src/plugins/system/dharma_universal_filter  "$SYS"
zip_dir src/plugins/task/dharma_universal_filter    "$TASK"

# Пакет: манифест и скрипт в корне, вложенные архивы в packages/.
PKG_ROOT="$WORK/pkg"
mkdir -p "$PKG_ROOT/packages"
cp package/pkg_dharma_universal_filter.xml package/script.php "$PKG_ROOT/"
cp "$DIST/$LIB" "$DIST/$MOD" "$DIST/$SYS" "$DIST/$TASK" "$PKG_ROOT/packages/"
zip_dir "$PKG_ROOT" "$PKG"

# Проверка: в именах записей не должно быть обратных слэшей, а манифест пакета лежит в корне.
for z in "$DIST"/*.zip; do
  if unzip -Z1 "$z" | grep -q '\\'; then
    echo "Ошибка: в $z найдены обратные слэши в путях" >&2
    exit 1
  fi
done
unzip -Z1 "$DIST/$PKG" | grep -qx 'pkg_dharma_universal_filter.xml' || { echo "Ошибка: манифест пакета не в корне" >&2; exit 1; }

echo "Собрано в $DIST/:"
ls -1 "$DIST"/*.zip | sed 's/^/ - /'
