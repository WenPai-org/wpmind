---
description: 部署代码到开发站点并清理缓存
---

# WPMind 部署工作流

每次代码修改完成后，执行以下步骤部署到开发站点：

## 1. 提交代码
```bash
git add . && git commit -m "commit message"
```

// turbo
## 2. 同步到站点
```bash
./deploy.sh
```

// turbo
## 3. 清理 PHP OPcache
```bash
sudo /etc/init.d/php-fpm-83 restart
```

## 注意事项
- 部署脚本会自动同步代码到 `/www/wwwroot/wpcy.com/wp-content/plugins/wpmind`
- 重启 PHP-FPM 可清除 OPcache 缓存
- 如需刷新浏览器 CSS/JS 缓存，需更新版本号（wpmind.php 中的 WPMIND_VERSION）
