# TxtHarbor · 文本港湾

**轻量级 PHP 留言与文本归档工具**，从 162100 留言本兼容改造。适用于个人留言、文本片段、代码片段和含特殊字符的长文本记录。

> **版权注意：** 原项目头部注明作者 162100.com，声明“仅供代码爱好者学习交流，禁用于商业目的，请保留此版权信息”。本仓库保留原代码版权声明；请勿未经原版权方许可自行换成 MIT、Apache 等许可证或用于商业目的。

## 特性

- 简洁的浅色/深色科技风，默认跟随系统主题，也可在右上角手动切换并记住偏好；手机和桌面自适应。
- 编辑框一键清空、一键粘贴，正文一键复制。
- 支持纯英文、数字和特殊字符；保留 `/`、`\\`、`|`、多空格、制表符、换行等原文。
- 发表成功自动回到列表，定位并高亮刚刚发布的留言。
- 管理员留言管理：单条删除、勾选批量删除、内容审核、回复。
- 保留旧版 GB2312/GBK 文本数据库的字节索引；新版使用 UTF-8 包装以无损存储特殊字符。
- 后台 GitHub Release 更新中心：可配置仓库和镜像地址、直连/代理/自动切换，校验 SHA-256 后备份并安装。

## 项目地址

- [GitHub](https://github.com/popowk/txtharbor)
- [国内代理](https://git.wksite.cn/popowk/txtharbor)

## 从既有 162100 / TxtHarbor 站点升级

1. **完整备份网站，包括 `config.php`、`data/`、`inc/badword.php`。**
2. 下载 `txtharbor-update.zip`（Release 附件），把里面 `txt/` 的文件覆盖到网站对应目录。
3. **不要覆盖/删除** `config.php`、`data/`、`__temp__/` 和自定义禁词表 `inc/badword.php`。
4. 登录后台后打开 **留言管理**：`index.php?admin_run=1`，或进入 **版本更新**：`update.php`。
5. 建议部署 PHP 7.4+；在线升级需要 `ZipArchive`、HTTPS 出站访问和相应代码目录写权限；没有 ZipArchive 可手动覆盖。

> 严禁将旧 `data/file.php` 转码后重写；索引是基于文件的字节偏移，不是字符位置。

## 新安装（仅供代码测试和开发）

1. 把源代码放在 PHP 网站根目录，复制 `config.example.php` 为 `config.php`。
2. 在 `config.php` 把 `$web['password']` 修改为你的管理密码摘要；可以用 `php tools/hash_password.php` 生成。**不要把管理密码或生成后的 `config.php` 提交到 GitHub。**
3. 创建可写的 `data/` 和 `__temp__/`，初始化：

   ```sh
   mkdir -p data __temp__
   printf '<?php die(); ?>' > data/file.php
   : > data/index.txt
   : > data/space.txt
   ```

4. 浏览 `index.php`，从 `admin.php` 登录后设置站点内容及审核规则。建议启用 HTTPS，定期离线备份。

旧项目的验证码、权限和数据库设计比较古老，**不建议在对抗性公网生产环境中未经安全审计直接开放**。

## 发布 GitHub 更新

每次正式发布都更新根目录 `VERSION`（如 `1.4.0`），然后运行：

```sh
python3 tools/build_release.py
```

得到 `dist/txtharbor-update.zip`。推送到 `main` 时，仓库的 `.github/workflows/release.yml` 会根据 `VERSION` 自动创建对应 Tag 和正式 Release，并上传 `txtharbor-update.zip`（需要仓库允许 GitHub Actions 创建 Release）。也可以手动发布，Tag 要与版本对应（如 `v1.4.0`），附件名必须为 `txtharbor-update.zip`。

下载路径：

- GitHub: `https://github.com/popowk/txtharbor/releases/download/v1.4.0/txtharbor-update.zip`
- 国内代理: `https://git.wksite.cn/popowk/txtharbor/releases/download/v1.4.0/txtharbor-update.zip`

在线安装要求 GitHub API 对该 Release 附件提供 `digest: sha256:...` 字段，且校验通过，否则只能手动下载覆盖，以免上传错误或代理异常导致破坏。新版更新中心的地址都可以修改，用户不必依赖固定仓库名。

## 隐私与备份

- `config.php` 存储私有管理设置，`data/` 存储留言与管理员会话记录，`__temp__/` 存储临时操作数据；这些均通过 `.gitignore` 排除。
- GitHub 公开仓库中**不要上传**上述目录中的真实文件、运行日志、Cookie 或密码。
- 在线安装只替换白名单代码文件，旧代码备份保存到站点根目录之外的服务器临时目录。

## 测试

```sh
php tests/text_regression.php
php tests/update_regression.php
```

## 致谢

本项目基于 **162100 留言本** 的原有功能、数据结构和源码改进，保留原作者的版权说明及使用限制。
