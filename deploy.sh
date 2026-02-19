#!/bin/bash
# WPMind 部署脚本
# 将开发目录的代码同步到 WordPress 插件目录

set -e

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

# 生成 .pot 翻译模板
echo "🌐 生成 .pot 翻译模板..."
mkdir -p "$SOURCE_DIR/languages"
wp i18n make-pot "$SOURCE_DIR" "$SOURCE_DIR/languages/wpmind.pot" --domain=wpmind --skip-audit --quiet

# 同步文件 (排除 .git 目录)
echo "📦 同步文件..."
sudo rsync -av --delete \
    --exclude='.git' \
    --exclude='deploy.sh' \
    --exclude='*.log' \
    "$SOURCE_DIR/" "$TARGET_DIR/"

# 修复权限
echo "🔧 修复权限..."
sudo chown -R www:www "$TARGET_DIR"
sudo chmod -R 644 "$TARGET_DIR"
sudo find "$TARGET_DIR" -type d -exec chmod 755 {} \;

echo ""
echo "✅ 部署完成!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
