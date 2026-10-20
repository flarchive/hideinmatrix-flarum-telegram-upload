# 发布与用户安装

## 分发方式

本扩展是 Composer 包 `hideinmatrix/flarum-telegram-upload`，支持 Flarum `^2.0`、PHP `^8.3`，依赖 FoF Upload `^2.0.1`。首个正式标签为 `v0.1.0`；扩展自身的版本号与 Flarum 主版本号独立。

Flarum 官方推荐通过公开 Git 仓库和版本标签发布，再提交到 Packagist。Packagist 索引仓库与标签，并非上传 ZIP 的网站。GitHub Release 附件用于手动下载和校验，不代替 Composer 分发。

参考：

- [Flarum 2.x 分发说明](https://docs.flarum.org/extend/distribution/)
- [Flarum 安装与更新扩展](https://docs.flarum.org/extensions/)
- [Packagist 提交与版本说明](https://packagist.org/about)

## 首发步骤

1. 完成测试、README、CHANGELOG、许可证与仓库公开前的配置检查。`js/dist/admin.js` 必须已经构建并提交；不提交真实站点配置、密钥、`vendor/` 或缓存。
2. 将 GitHub 仓库公开。此操作会公开完整的 Git 历史，需要仓库所有者确定发布。
3. 将 CHANGELOG 中的计划版本改为正式版本和发布日期，提交后对该提交创建并推送标签：

   ```sh
   git tag -a v0.1.0 -m "Telegram Upload v0.1.0"
   git push origin v0.1.0
   ```

   `composer.json` 不写死 `version`，由 Composer 从标签读取。

4. 登录 Packagist，在 [Submit](https://packagist.org/packages/submit) 填入公开仓库 URL：`https://github.com/HideInMatrix/flarum-telegram-upload.git`。确认包名、版本和 Flarum 2.x 依赖正确，再提交。
5. 为这个仓库配置 Packagist 自动更新。后续发布只需提交代码、测试并推送新的语义版本标签。Webhook 凭据只放在服务设置中，不写入仓库或文档。
6. 用普通用户安装命令确认 Packagist 能解析正式版本，再将现有论坛切换为正式来源。可另行在 Flarum Community 的 Extensions 分类发布介绍；社区介绍不是包安装的前提。

## 用户安装与更新（Packagist 首发成功后）

在论坛根目录执行：

```sh
composer require hideinmatrix/flarum-telegram-upload:"^0.2"
php flarum cache:clear
```

在 Flarum 后台启用 FoF Upload 和 Telegram Upload，然后填写站点 URL、存储方式、API Key 以及 Telegram 所需的频道 ID。Composer 自动处理 PHP 包依赖；是否允许普通用户上传、下载仍由论坛管理员设置。

后续兼容更新：

```sh
composer update hideinmatrix/flarum-telegram-upload
php flarum cache:clear
```

安装了 Flarum 2.x `flarum/extension-manager` 的站点，也可以在其管理界面按 Composer 包名安装、检查更新和更新。仅有普通扩展设置页不代表已安装 Extension Manager。

## 从当前私有路径包切换

当前站点曾通过 `telegram-upload-local` 路径仓库安装 `dev-main`。仅在 GitHub 推送新代码不会自动替换此本地目录；该仓库的优先级还可能遮蔽 Packagist 的正式版本。

**以下操作等到 Packagist 正式版本可用后再执行**，先备份论坛 `composer.json`、`composer.lock` 和数据库，保留已有插件设置。不要执行“清除数据”。

移除当前自定义来源并切换稳定版本约束：

```sh
composer config --unset repositories.telegram-upload-local
# 仅在之前配置了同名 VCS 来源时执行下一行：
composer config --unset repositories.telegram-upload
composer require hideinmatrix/flarum-telegram-upload:"^0.2"
php flarum cache:clear
```

先用 `composer require hideinmatrix/flarum-telegram-upload:"^0.2" --dry-run` 检查解析范围，再执行实际安装。若有长驻 worker/realtime 进程，更新成功后通过现有容器管理方式重启相关进程以加载新代码。无需修改 FoF Upload 源码，也不用把 ZIP 手动覆盖进 `vendor/`。

本地路径源目录可在确认正式版本加载正常后归档；附件与插件配置仍保存在论坛数据库中。

## 后续自动发布（v0.1.1 起）

提交代码、通过本地测试并更新 CHANGELOG 后，推送新的 `vX.Y.Z` 标签。仓库的 `Publish Telegram Upload` workflow 会先运行完整 PHP 测试和非 root 目录权限复现测试，再生成 ZIP、tar.gz 和 SHA256SUMS，使用当前标签的变更记录发布 GitHub Release。测试失败时不会创建 Release。

Composer 版本继续从 Git 标签获取，不修改 `js/package.json` 中供前端构建使用的私有包版本号。Packagist 的同步仍由其 GitHub webhook 负责，GitHub Release 不代表 Packagist 一定已经同步；发布后应分别检查二者。权限修复属于服务器操作，workflow 不连接或修改用户论坛服务器。
