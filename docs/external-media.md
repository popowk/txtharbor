# TxtHarbor 外部图片存储适配层（预留）

> v1.5.0 **未启用图片上传**。本文件是未来接入网盘的边界说明，不意味着当前已经支持粘贴图片或外链图片预览。

## 设计目标

- 未来可支持 WebDAV、S3 兼容对象存储、网盘官方 API（例如阿里云盘等）作为**图片数据的存储位置**；不把图片二进制写入 `data/file.php` 或 `data/entry_meta.php`。
- 每条留言的 `data/entry_meta.php` 元数据预留 `media: []`。对象描述符包含 `provider`、`object_id`、`filename`、`mime`，而非完整 URL、访问密钥或网盘账号。
- 适配器入口见 `inc/media_store.php`。目前所有适配器均禁用，无上传、删除、拉取、预签名接口被调用。

## 后续适配器设计建议

1. 通过站点管理员配置启用一种存储后端。访问密钥仅存储在服务端单独保护的配置文件中，文件权限为 0600，不提交 GitHub。
2. 前端粘贴图片后先发起带 CSRF 的授权申请；服务端验证登录身份、格式、文件魔数、大小和单条数量，然后创建图片对象，返回不可猜测的对象 ID。
3. Markdown 编辑器仅插入本站内部引用（例如 `media:<id>`），不要直接插入长期有效的公开网盘链接。
4. 浏览器请求附件必须经过服务器的留言权限检测：审核状态、有效期、密码解锁与管理员身份。这一点与原始 Markdown 的权限一致。
5. 后端可选择短期签名链接或受控代理流式读取。不要在未授权网页或 JSON 中返回网盘直链；缓存设为 `private, no-store`。
6. 删除留言/修改有效期后回收关联对象，按存储后端策略记录失败重试；避免残留外网公开资源。
7. 配置独立的文件类型白名单、文件大小、上传频率限制；限制重定向与对象 ID，避免 SSRF 和目录穿越。

## 建议后端接口（未来实现）

```
provider.prepareUpload(context, mime, size) -> ticket
provider.completeUpload(ticket) -> {provider, object_id, filename, mime}
provider.authorizeRead(object_id, ttl_seconds) -> short_lived_url_or_stream
provider.delete(object_id) -> success_or_retryable_error
```

所有 `authorizeRead` 调用由 TxtHarbor 在验权之后执行，适配器不应自行信任客户端提供的 ID 或 URL。
