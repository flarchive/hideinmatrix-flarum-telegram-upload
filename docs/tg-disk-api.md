# tg-disk 上传接口核对

核对日期：2026-10-20。依据 [HideInMatrix/tg-disk 提交 0e2ba5e](https://github.com/HideInMatrix/tg-disk/commit/0e2ba5e30cafd5e55adebc502355de28df2779c2)。未修改 tg-disk 或线上论坛配置。

## 站点 URL 与存储方式

插件只需配置 HTTPS 站点 URL（如 `https://pan.example.com`），选择新上传使用 Telegram 或 Cloudflare R2。

| 后端 | 上传接口 | multipart 字段 | 保存的完整直链 |
| --- | --- | --- | --- |
| Telegram | `POST /api/telegram/send` | `file`、`fileName`、`chatId` | `{站点}/file/{file_id}` |
| R2 | `POST /api/r2/send` | `file`、`fileName` | `{站点}/r2/{file_id}` |

两种接口均返回 `code: 200`、`data.file_id`、`data.file_size`，不返回完整文件 URL。因此插件使用**上传当时**的站点 URL 和后端拼接一次，保存到附件的 `url` 字段，后续切换配置不重新生成旧 URL。R2 的 `file_id` 是 UUID 加原扩展名的对象 key。R2 上传传入原文件名及 MIME；Telegram 继续用 UUID `.bin` 触发 document 保存，避免图片压缩，论坛保留原文件名。

## 鉴权

两个上传接口在解析文件前均调用 `assertApiKey`。tg-disk 私密 `NUXT_API_KEY` 至少 32 个字符，论坛通过 `X-API-Key` 请求头提交。缺少服务端有效密钥返回 503，认证失败返回真实 HTTP 401。上传不跟随重定向，响应错误不回显密钥或上游正文。

文件直链使用 `/file/`、`/r2/`，读取不附加 API Key，由 tg-disk 的 host/referer 规则管理。论坛页面加载直链时，需在 tg-disk 防盗链配置中允许相应论坛域名。旧论坛下载入口仍执行 FoF 权限检查，读取新记录时使用已保存的绝对 URL；旧 Telegram 代理记录继续按 file_id 读取 `/file/`，不受新上传选择 R2 的影响。

## 索引与删除

上传与 tg-disk 文件管理索引是两步操作。插件不调用 `POST /api/files`，只把附件索引保存到论坛。两种后端的删除都仅清理论坛记录，不调用 tg-disk、Telegram 或 R2 的远程删除。

测试使用模拟 HTTP 覆盖 Telegram/R2 上传、完整 URL 持久化、切换配置、鉴权和旧链接兼容；未使用真实密钥向线上存储上传测试附件。
