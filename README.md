# Telegram Upload for Flarum 2.x

通过 **FoF Upload → tg-disk → Telegram** 存储论坛附件的独立 Flarum 扩展。

使用官方 `@flarum/cli` **3.0.13** 的 Flarum 2.x 扩展模板建立，采用 Composer 包、PSR-4 自动加载、标准 `extend.php`、服务提供者和事件监听器。项目不包含论坛安装文件或实际站点配置。[模板来源](docs/scaffold.md)

## 功能与范围

- 依赖 PHP 8.3+、Flarum `^2.0`、FoF Upload `^2.0.1`。
- 支持常见图片、PDF、ZIP、7z、gzip 和纯文本；每个文件最多 **10 MiB**。
- Telegram 使用 document 保存，避免 Telegram 压缩图片和 GIF。
- 下载流式经过论坛，保留 FoF Upload 的下载权限、标签限制和记录功能。
- 兼容旧适配器生成的 `/api/tinker-telegram/{uuid}` 地址。
- 没有配置缩放、水印时，图片保留原始字节。
- **删除仅移除论坛媒体记录**，不会请求 tg-disk 或 Telegram 删除文件；Telegram 副本及其直链不受影响。清理记录不需要上传 API Key。
- 缩略图暂不单独存储，图片预览直接读取完整图片。

## 安装

在论坛根目录执行：

```sh
composer require hideinmatrix/flarum-telegram-upload:"^0.1"
```

发布流程、后续更新、Extension Manager 和当前路径包切换方式参见 [发布说明](docs/releasing.md)，版本变更参见 [CHANGELOG](CHANGELOG.md)。

在管理后台启用 **Telegram Upload**，进入该扩展的配置页填写并保存：

| 字段 | 填写内容 |
| --- | --- |
| 上传接口 URL | `https://your-tg-disk.example.com/api/telegram/send`，必须是完整 HTTPS 接口地址 |
| TG_DISK_API_KEY | 与 tg-disk 服务端私密 `NUXT_API_KEY` 完全一致，至少 32 个字符 |
| 上传频道 ID | Telegram 数字频道 ID，例如 `-1000000000000` |

密钥输入框使用密码样式，设置仅管理员可读，不会写入论坛公开 API。不要填写 `NUXT_SESSION_PASSWORD`，也不要提交真实密钥到仓库。论坛不需要 Telegram Bot Token。保存立即作用于后续上传请求，无需重新创建容器。缺少有效配置时，Telegram 上传存储不会启用。

旧版的 `config.php` 中 `telegram-upload` 配置和 `TG_DISK_BASE_URL`、`TG_DISK_CHAT_ID`、`TG_DISK_API_KEY` 环境变量仍兼容；**后台已保存的字段优先，包括空值**。建议统一使用后台配置，移除旧配置以免混淆。扩展不自动加载 `.env`。

安装或升级后执行：

```sh
php flarum cache:clear
```

在 **FoF Upload → 文件类型** 中，将需要的规则的存储位置选择为 Telegram；大小上限设置为 **10240 KB**。图片选择“完整图片预览模板”，其他文件选择“默认文件下载模板”。本扩展不会自动改变用户组权限，按论坛需要分别设置上传和下载权限。

**如果服务器已部署旧的 `tinker-telegram` 目录适配器，请先阅读 [迁移说明](docs/migration.md)，避免重复注册路由。**

## 本地开发与测试

```sh
composer install
composer validate --strict
composer lint
composer test:unit
# 只对隔离的测试数据库执行，会清空指定的测试库：
composer test:setup
composer test:integration
```

PHPUnit 集成测试使用 Flarum 官方 `flarum/testing`。测试初始化默认使用 SQLite；不要指向生产数据库。测试目录与缓存均已被 Git 忽略。

没有本地 PHP 时，先构建包含 Flarum 所需 GD 扩展的 Composer 测试容器：

```sh
docker build -f docker/Dockerfile.test -t flarum-telegram-upload-test:local .
docker run --rm -v "$PWD:/app" -w /app flarum-telegram-upload-test:local composer install
# 首次创建隔离的 SQLite 测试数据库：
docker run --rm -v "$PWD:/app" -w /app \
  -e FLARUM_TEST_TMP_DIR_LOCAL=/app/tests/integration/tmp \
  flarum-telegram-upload-test:local composer test:setup
docker run --rm -v "$PWD:/app" -w /app \
  -e FLARUM_TEST_TMP_DIR_LOCAL=/app/tests/integration/tmp \
  flarum-telegram-upload-test:local composer test
```

测试验证后台保存与校验、普通用户不能修改密钥、清空配置不会回退到旧密钥、上传鉴权、密钥不进入公开 API 数据、扩展启动与依赖注入、旧 URL、访客下载限制、授权图片响应以及媒体记录删除。删除沿用 FoF 的权限校验，仅清理选中的论坛记录，不发出远程请求；其他存储适配器沿用原有删除行为。测试对 tg-disk 使用模拟 HTTP，不会发送真实附件或删除 Telegram 消息。

前端源码沿用官方 CLI 的 JavaScript 管理端结构；修改后台界面后在 `js/` 执行 `npm ci && npm run build`，并提交 `js/dist/admin.js`，以便 Composer 安装直接使用。

## 运行条件

PHP 运行用户必须能写入论坛存储目录。FoF Upload 即使使用 Telegram 存储，也会初始化 `storage/private-shared/files`；权限错误会在 tg-disk 调用前触发 500。目录修复命令和 v0.1.1 的只读检查工具参见 [上传错误排查](docs/troubleshooting.md)。

tg-disk 需要提供 `POST /api/telegram/send` 和 `GET /file/{file_id}`，上传结果包含 `code`、`data.file_id`、`data.file_size`；Bot 及频道权限在 tg-disk 侧配置。上传通过服务端 `X-API-Key` 请求头鉴权，401/403 返回明确提示，不转发上游响应中的敏感内容。文件读取沿用 tg-disk 的 host/referer 规则，不附加 API Key。服务器必须能访问 tg-disk，tg-disk 的文件访问规则也需允许论坛服务端读取。10 MiB 是当前对接接口的限制。[接口核对记录](docs/tg-disk-api.md)

论坛数据库仍保存附件索引和权限关联，需与扩展配置一起备份。附件读取依赖论坛、tg-disk 和 Telegram 的可用性，下载流量会经过论坛。tg-disk 本身的上传鉴权和文件直链访问策略由其服务端负责。

## License

MIT，见 [LICENSE.md](LICENSE.md)。
