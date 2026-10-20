# Changelog

## 0.2.0 - 2026-10-20

### Features
- Configure a tg-disk site URL and choose Telegram or Cloudflare R2 for new uploads. R2 needs no Telegram channel ID.
- Persist absolute attachment URLs at upload time: `/file/{id}` for Telegram and `/r2/{key}` for R2. New image previews use these direct URLs.
- Preserve R2 filenames and MIME types while keeping Telegram document uploads byte-preserving.

### Compatibility
- Keep existing URLs unchanged when switching storage or site settings. Preserve the legacy forum proxy route and old full-endpoint configuration.
- Keep forum-only media deletion for both storage backends and retain FoF authorization checks.
- Sites constrained to `^0.1` must explicitly require `^0.2` to receive this release. Direct URL access is governed by tg-disk; it does not use the forum download permission gate.

## 0.1.2 - 2026-10-20

### Fixes
- Support FoF Upload 2.0.2, which narrows the deletion handler return type to `bool`. Keep compatibility with 2.0.1 and preserve the upstream authorization and storage deletion behavior. Telegram deletion still removes only the forum media record.

### Tests
- Verify successful and failed deletion through other storage adapters in addition to Telegram record cleanup.

### Maintenance
- Validate branch builds with a valid Composer development version and report concise test errors before publishing release archives.

## 0.1.1 - 2026-10-20

### Fixes
- Document the confirmed FoF Upload storage permission failure that occurs before a Telegram upload: `storage/private-shared/files` must be writable by the PHP worker user. Updating this package does not change existing server directory ownership; follow `docs/troubleshooting.md` to repair it.
- Add a read-only storage preflight script that reports blocked paths and refuses root checks, avoiding misleading permission results.

### Tests
- Exercise the complete PNG upload API, outbound bytes and persisted media record.
- Reproduce the root-owned private storage failure as the PHP worker user and verify the documented permission repair.

### Maintenance
- Publish GitHub Releases with archives and SHA-256 checksums automatically after tag-triggered tests pass.

## 0.1.0 - 2026-10-19

- Initial Composer extension for Flarum 2.x, generated from the official Flarum CLI scaffold.
- Telegram attachment storage through FoF Upload 2.x and tg-disk, with a 10 MiB limit.
- Admin settings for the full upload endpoint URL, `TG_DISK_API_KEY` and upload channel ID.
- Server-side API key authentication; no key in the public forum API or file download requests.
- Streamed downloads through the forum, retaining FoF permissions and legacy attachment routes.
- Preserve original image bytes when no resizing or watermarking is configured.
- Delete media records from the forum without deleting Telegram content or sending a remote request, including when upload configuration is missing.
- Retain FoF delete permissions and the original behavior of other storage adapters.
