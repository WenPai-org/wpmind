#!/bin/bash
# WPMind 部署脚本
# 将开发目录的代码同步到 WordPress 插件目录

set -euo pipefail

APPLY=false
if [ "${1:-}" = "--apply" ]; then
    APPLY=true
fi

SOURCE_DIR="$(cd "$(dirname "$0")" && pwd)"
TARGET_DIR="/www/wwwroot/wpcy.com/wp-content/plugins/wpmind"

echo "🚀 WPMind 部署脚本"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "源目录: $SOURCE_DIR"
echo "目标目录: $TARGET_DIR"
echo ""

# 检查目标目录是否存在
if [ ! -d "$TARGET_DIR" ]; then
    echo "❌ 目标目录不存在: $TARGET_DIR"
    exit 1
fi

# 同步发布文件；默认只预览，必须显式传 --apply 才写入目标目录。
echo "📦 检查发布文件..."
RSYNC_ARGS=(
    -av --delete
    --exclude='.git'
    --exclude='.forgejo'
    --exclude='node_modules'
    --exclude='vendor'
    --exclude='tests'
    --exclude='test-results'
    --exclude='docs'
    --exclude='deploy.sh'
    --exclude='composer.*'
    --exclude='package*.json'
    --exclude='phpunit.xml*'
    --exclude='phpcs.xml*'
    --exclude='*.log'
)

if [ "$APPLY" != true ]; then
    echo "ℹ️  dry-run：未写入目标目录；确认后使用 ./deploy.sh --apply"
    sudo rsync "${RSYNC_ARGS[@]}" --dry-run "$SOURCE_DIR/" "$TARGET_DIR/"
    exit 0
fi

# 只在实际发布时生成翻译模板。
echo "🌐 生成 .pot 翻译模板..."
mkdir -p "$SOURCE_DIR/languages"
wp i18n make-pot "$SOURCE_DIR" "$SOURCE_DIR/languages/wpmind.pot" --domain=wpmind --skip-audit --quiet

sudo rsync "${RSYNC_ARGS[@]}" "$SOURCE_DIR/" "$TARGET_DIR/"

# 修复权限
echo "🔧 修复权限..."
sudo chown -R www:www "$TARGET_DIR"
sudo find "$TARGET_DIR" -type f -exec chmod 644 {} \;
sudo find "$TARGET_DIR" -type d -exec chmod 755 {} \;

echo ""
echo "✅ 部署完成!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
