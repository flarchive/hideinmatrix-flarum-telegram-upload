# 从站点内适配器迁移

以下步骤适用于从旧站点适配器迁移，操作前备份。

1. 备份论坛数据库、`composer.json`、`composer.lock`、根目录 `extend.php`、现有 `tinker-telegram/` 目录及其配置。
2. 使用 Composer 安装本扩展，暂不启用。
3. 在 Telegram Upload 后台配置页填写 tg-disk 站点 URL、存储方式及 `TG_DISK_API_KEY`；仅 Telegram 需要上传频道 ID。站点 URL 不含 `/api/telegram/send`；密钥与 tg-disk 私密 `NUXT_API_KEY` 一致且至少 32 个字符。后台保存成功后，可删除旧私密配置和容器中相应 TG_DISK 环境变量，保留其他应用环境变量。
4. 从论坛根 `extend.php` 中移除加载 `tinker-telegram/extend.php` 的那一项，保留其他扩展项。不要同时加载旧适配器与本扩展，否则会重复注册同一下载路由。
5. 在 Flarum 后台启用 `Telegram Upload` 并执行 `php flarum cache:clear`。
6. 检查 FoF Upload 的 MIME 规则仍使用 `telegram`，文件大小上限为 10240 KB，按实际用户组测试图片与压缩包的读取。

本扩展保留 `upload_method=telegram`、Telegram `remote_id`、旧路由名称和路径。无需改写历史帖子中的链接，也不迁移或清空附件数据库。旧代码目录可以先留作回滚备份，不再加载。

回滚时先禁用新扩展，再恢复原来的根 `extend.php` 加载项和配置并清缓存。不要在还有附件依赖本扩展时直接删除扩展或附件索引。

## 删除功能

媒体库中的“删除”仅删除选中的论坛附件记录，不删除 Telegram 上的文件，也不向 tg-disk 发出远程删除请求。旧版因未实现远程删除而拒绝操作的提示已移除。此操作不依赖上传密钥、上游可用性或当前 MIME 映射，仍沿用 FoF Upload 原有的删除权限校验。其他存储适配器的删除行为不变。

这里的软删除是指保留远端内容；论坛媒体数据库行由 FoF 按原有流程移除，不能再从“我的媒体”中选择该记录。本扩展不撤销或封禁已分发的 Telegram/tg-disk 直链，不清理帖子中的旧文本或链接。

## 已安装此 Composer 扩展时升级

tg-disk 启用 API Key 后，旧上传客户端会收到 401。升级本包并清缓存，在 Telegram Upload 后台填写站点 URL、存储方式、API Key 以及 Telegram 所需的频道 ID。后台字段覆盖旧 `config.php` 和环境变量，即使保存为空也不回退。密钥作为管理员设置保存在论坛数据库中，需保护数据库与备份；只有管理员设置载荷包含密钥，论坛公开 API 不包含。历史附件读取仍走原路由。

若需清理第三方包源码，可先备份、禁用相关扩展后执行 `composer reinstall fof/upload hideinmatrix/flarum-telegram-upload`，再启用扩展并清缓存。Composer 重新安装包不等于 Flarum 的“清除数据”，不要清除附件表或卸载扩展数据。FoF Upload 保持官方原版，适配行为由独立扩展实现。

## 0.2.x：完整直链与 R2

执行 `composer require hideinmatrix/flarum-telegram-upload:"^0.2" --with-all-dependencies` 后清缓存。填写 tg-disk 站点地址（如 `https://pan.example.com`），选择 Telegram 或 R2。已有完整上传接口配置仍可读取，新站点字段保存后优先；清空新站点字段会停用上传，不回退到旧值。

新文件在论坛数据库保存上传当时的完整 URL：Telegram 使用 `/file/{id}`，R2 使用 `/r2/{key}`。切换后端、频道或站点地址不会重写已有 URL。内部 `upload_method=telegram` 保留为本插件兼容键，`path` 的 `telegram/` 或 `r2/` 前缀记录实际存储后端。

0.1.x 的旧帖子与旧媒体记录不做批量迁移。原 `/api/tinker-telegram/{uuid}` 路由继续保留；切换到 R2 后，旧 Telegram ID 仍访问 `/file/{id}`。尚未保存独立直链的旧记录继续依赖配置的 Telegram 服务站点，迁移站点时需保留原服务的文件访问能力。

两种后端的媒体删除都仅移除论坛索引，不调用上游删除。新直链由 tg-disk 控制访问，不经过论坛的下载权限入口；启用防盗链时应允许论坛域名。
