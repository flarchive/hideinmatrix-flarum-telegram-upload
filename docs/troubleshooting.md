# 上传错误排查

## 500：无法创建 storage/private-shared/files

若 Flarum 日志包含以下异常：

```text
League\Flysystem\UnableToCreateDirectory
Unable to create a directory at .../storage/private-shared/files
mkdir(): Permission denied
```

这是论坛存储目录权限问题。FoF Upload 2.0.1 在构造上传处理器时，会先初始化 `private-shared` 本地磁盘；即使当前文件选择 Telegram 存储，也需要完成该步骤。因此异常会在 Telegram 适配器和 tg-disk 请求之前发生。

在使用 `www-data` 运行 PHP 的容器中，以 root 执行一次：

```sh
cd /var/www/html
mkdir -p storage/private-shared/files
chown -R www-data:www-data storage/private-shared
chmod 700 storage/private-shared storage/private-shared/files
```

然后重新上传。目录属主应与 PHP 进程实际用户一致；其他镜像请使用其实际用户。私有目录保持不可公开读取，不需要将权限改为 777，也不需要修改 FoF Upload 源码、API Key 或 Telegram 频道。

本扩展不会在 HTTP 请求中自动提权、修改目录属主或放宽权限。仅更新 Composer 包不能修复已有挂载目录的属主；需要服务器管理员执行上面的操作。该目录不应映射到网站公开目录。

### 只读预检查（v0.1.1 起）

使用实际 PHP 进程用户运行：

```sh
cd /var/www/html
su-exec www-data:www-data php vendor/hideinmatrix/flarum-telegram-upload/scripts/check-storage.php /var/www/html
```

检查 `storage/tmp`、`storage/private-shared/files`、`public/assets`。输出 `BLOCKED` 时需要处理对应路径。不存在但可由当前用户创建的目录会显示 `OK (can be created)`。退出码 0 表示通过，1 表示路径被阻塞，2 表示用法不正确或以 root 运行。

脚本不创建目录、不修改权限、不加载论坛配置或密钥。以 root 检查会得到不准确的权限结论，因此脚本会要求改用 PHP 用户。

## 获取 PHP 异常日志

在论坛根目录查看 `storage/logs/` 下与失败时间对应的 `flarum-YYYY-MM-DD.log`。浏览器显示的 `{"errors":[{"status":"500","code":"unknown"}]}` 未包含根因；需要日志中的异常名称、错误信息和开头几帧调用栈。分享日志前遮盖密码和密钥。
