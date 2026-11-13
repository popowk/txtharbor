# TxtHarbor 安全说明与 v1.5.7 代码审查

本文件是源代码静态审查和隔离测试的记录，**不是公网渗透测试**，不能保证线上环境不存在漏洞。

## v1.5.7 已处理

- **伪造 IP 绕过发布限额：** 不再信任未认证的 `HTTP_X_FORWARDED_FOR` / `HTTP_CLIENT_IP`，默认使用 Web 服务器提供的 `REMOTE_ADDR`。每日限额数据文件名使用 SHA-256 哈希，不将原始 IP 直接拼接进文件路径。注意：如果网站前置 CDN 或反向代理，需由可信代理层正确提供访客地址，不能直接信任客户端自填头。
- **清理每日计数误删密码验证记录：** 旧版可能删除整个 `__temp__/password_attempts`，现在仅清理旧的八位日期目录。
- **管理员在线猜密钥：** 增加基于实际连接 IP 的登录失败次数控制，连续多次错误后暂时限制，服务端记录无法保存时不允许跳过验证。
- **旧审核接口缺少跨站请求防护：** 单条 GET 核准及批量 POST 核准都要求原有的管理员防伪令牌。
- **旧提示层 HTML 注入：** 浏览器使用文本节点显示动态提示，而不解析为 HTML；未知定时回调字符串不再执行。
- **Markdown：** 原始 HTML 始终编码；链接仅接受 http/https，外部图片仅接受 HTTPS 公网域名并需要用户点击加载，不自动发起第三方请求。不接受 JavaScript/data/file、本地 IP、无点主机名和 SVG 链接作为图片源。支持 GFM 风格表格，内容逐单元格安全编码。
- **长文本：** 恢复 `MAXHEIGHT_SIZE` 在列表页的折叠，无 JavaScript 时不截断正文；详情页完全展示，复制保留原始 Markdown。

## 尚需单独规划的安全加固

1. **管理员密码哈希：** 出于旧 `config.php` 兼容，目前仍保留 `sha1(md5(password))` 派生方式，不具有现代抗离线破解能力。建议通过兼容迁移将管理员密钥改为 `password_hash()` (Argon2id 或 bcrypt)，同时强制较长随机密码。不要直接替换旧配置格式而造成无法登录。
2. **Nginx / 代理配置：** `data/`、`__temp__/` 必须禁止直接通过 HTTP 下载。项目自带 `.htaccess` 仅对启用相应规则的 Apache 生效。Nginx 示例：

   ```nginx
   location ~ ^/(?:data|__temp__)(?:/|$) { return 404; }
   location ~ ^/(?:tests|tools|docs)(?:/|$) { return 404; }
   ```

   若网站位于子目录，应按站点部署路径调整 location。配置 HTTPS / HSTS，并避免将 Web 根目录映射为服务器共享的临时目录。
3. **公开留言防刷：** 旧验证码使用客户端状态，不足以抵御自动化，建议迁移为服务端验证码或 Turnstile + 服务器验证。现有 `DAY_SIZE` 是基础限额，不能作为唯一滥用防护。建议在路由器、CDN / WAF 上配合限速。
4. **更新供应链：** 在线升级依赖配置的 GitHub 项目、Release 文件和 SHA-256 完整性；摘要只能证明包与 Release 元数据一致，不能替代仓库所有者身份和发布签名。只使用可信项目，不开放给非管理员修改更新来源的权限。
5. **CDN/反向代理：** 身份 Cookie 的 Secure 属性应与实际公网 HTTPS 一致，确保代理不会缓存私人响应或把敏感页面暴露在 HTTP 上。
6. **上传与备份：** 目前 Markdown 图片只展示外部链接，不上传/代理图片；未来对接网盘必须单独处理签名 URL、鉴权、外链有效期和第三方跟踪问题。

## 检查办法

```bash
php tests/text_regression.php
php tests/entry_regression.php
php tests/auth_regression.php
php tests/markdown_security_regression.php
node tests/ui_regression.js
python3 tools/build_release.py
```

建议上线前由站点管理员进行 HTTPS、Nginx 禁止目录访问、PHP Session、Cookie、公开接口限流和管理员登录/退出的实际浏览器回归测试。
