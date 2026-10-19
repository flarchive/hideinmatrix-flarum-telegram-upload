# Changelog

## Unreleased — planned v0.1.0

- Initial Composer extension for Flarum 2.x, generated from the official Flarum CLI scaffold.
- Telegram attachment storage through FoF Upload 2.x and tg-disk, with a 10 MiB limit.
- Admin settings for the full upload endpoint URL, `TG_DISK_API_KEY` and upload channel ID.
- Server-side API key authentication; no key in the public forum API or file download requests.
- Streamed downloads through the forum, retaining FoF permissions and legacy attachment routes.
- Preserve original image bytes when no resizing or watermarking is configured.
- Delete media records from the forum without deleting Telegram content or sending a remote request, including when upload configuration is missing.
- Retain FoF delete permissions and the original behavior of other storage adapters.

The initial public release and Packagist submission are pending. No stable tag has been published yet.
