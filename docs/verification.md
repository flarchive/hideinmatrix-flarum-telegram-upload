# 本地验证记录

日期：2026-10-20。

环境：基于官方 `composer:2` 并安装 GD 的 `docker/Dockerfile.test` 容器，PHP 8.5.11；Composer 安装解析到 Flarum 2.0.2、FoF Upload 2.0.2（同时已验证 2.0.1）、flarum/testing 2.0.1。SQLite 测试数据库位于本项目被 Git 忽略的 `tests/integration/tmp/`。完整论坛 API 启动需要 GD，原始 Composer 镜像未包含此扩展。

已执行：

- `composer validate --strict`：通过。
- `composer lint`：PHP 语法检查通过。
- `npm run build`：Flarum 官方 webpack 配置的管理端生产构建通过。
- `composer test:unit`：32 项测试、65 项断言通过。
- `composer test:integration`：15 项测试、51 项断言通过。
- `sh tests/permissions.sh`：在隔离测试容器中以实际 `www-data` 用户复现目录权限错误，再验证修复命令有效。

覆盖配置缺失与 URL 校验、上传 multipart 原始字节、Telegram document 文件名、上游失败及大小不一致、超限请求拦截、下载响应和文件名、真实 Flarum 扩展启动、旧 URL 兼容、访客拒绝、管理员图片读取及论坛媒体记录删除。

本次新增覆盖：API Key 缺失/过短/控制字符拒绝、上传 `X-API-Key`、401/403 响应不泄露密钥或上游正文、禁止重定向转发密钥、公开文件请求不带密钥，以及论坛公开 API 不暴露密钥。

PHP 8.5 下，上游 `middlewares/utils` 的可空参数声明触发 3 条 deprecation 提示；没有测试错误或失败。未修改依赖源码以隐藏这些提示。

本轮使用模拟 tg-disk HTTP，不访问真实 Telegram。新增 Flarum 2.x Admin extender 配置页及上传接口 URL、频道 ID、API Key 校验；验证管理员保存、游客和普通成员拒绝修改、数据库设置优先级、清空密钥以及数据库密钥不出现在公开 API。另验证 Telegram 记录删除成功且无远程 HTTP、无上传配置仍可清理、其他记录保留、游客和普通成员不能绕过原有删除权限，以及其他存储删除失败时不误删记录。未改变用户组权限。生产迁移参见 `docs/migration.md`。

GitHub Actions 的干净依赖安装已验证 FoF Upload 2.0.2；修复删除方法返回类型兼容，Telegram 删除仍只清理论坛记录，并保留上游权限检查。
