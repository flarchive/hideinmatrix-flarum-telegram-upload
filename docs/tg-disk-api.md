# tg-disk API Key 对接核对

核对日期：2026-10-19。依据 [HideInMatrix/tg-disk 提交 0e2ba5e](https://github.com/HideInMatrix/tg-disk/commit/0e2ba5e30cafd5e55adebc502355de28df2779c2)。本次未修改 tg-disk 或线上论坛配置。

## 上传

[`assertApiKey`](https://github.com/HideInMatrix/tg-disk/blob/0e2ba5e30cafd5e55adebc502355de28df2779c2/server/utils/apiKey.ts) 要求服务端私密 `NUXT_API_KEY` 至少 32 个字符，外部客户端通过 `X-API-Key` 请求头提交。缺少有效服务端配置返回 503，认证失败返回 401。浏览器管理员会话是另一种入口，论坛服务端无需模拟登录。

[`POST /api/telegram/send`](https://github.com/HideInMatrix/tg-disk/blob/0e2ba5e30cafd5e55adebc502355de28df2779c2/server/api/telegram/send.post.ts) 在解析上传前调用鉴权，multipart 字段仍为 `file`、`fileName`、`chatId`，上限 10 MiB。成功结构仍含 `code: 200`、`data.file_id`、`data.file_size`，因此本扩展只需新增请求头及认证错误处理，现有上传格式不需要调整。

扩展继续使用 UUID `.bin` 文件名触发 Telegram document 保存，论坛保留原文件名。API Key 只从论坛 config.php 或容器环境变量读取，不放入 URL、multipart、前端或公开 API；上传不跟随重定向。

## 读取、索引与删除

`GET /file/{file_id}` 仍由 tg-disk 的文件访问规则管理，未调用 `assertApiKey`。本扩展不向文件读取请求附加密钥，论坛入口仍执行 FoF Upload 下载权限校验。

Telegram 上传与 tg-disk 文件管理索引是两步操作；本扩展只执行上传并将附件索引保存在论坛，不调用 `POST /api/files`，因此论坛附件不会自动列入 tg-disk 文件管理页。

tg-disk 的 `DELETE /api/files/:id` 依赖已有索引的 `message_id`、`chat_id`，且 Telegram 删除失败时仍可能删除索引。因此不能把该接口的索引删除结果直接当作 Telegram 附件已删除。本扩展仍明确提示不支持远程删除。

## 服务端与文档结论

本次接入所需的鉴权检查已经存在，未发现需要为该上传流程修改 tg-disk 服务端的接口差异。tg-disk README 中部分旧 curl / JavaScript 上传示例尚未附加 `X-API-Key`，使用时需补充请求头；不要关闭服务端鉴权来兼容旧客户端。

扩展测试使用模拟 HTTP，验证了请求与错误处理；未使用真实密钥执行线上 Telegram 上传。
