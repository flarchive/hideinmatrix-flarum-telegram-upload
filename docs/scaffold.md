# Scaffold provenance

Generated with the official @flarum/cli 3.0.13 init command on 2026-10-19.

Flarum 2.x extension with PHP unit/integration testing, Git configuration, GitHub Actions, EditorConfig, StyleCI and locales. The initial backend-only scaffold was extended with the official CLI JavaScript admin entry structure and `flarum-webpack-config` 3.x for the standard Flarum 2.x declarative Admin settings extender. The built admin bundle is committed for Composer installations.

Official references:
- https://docs.flarum.org/extend/start/
- https://github.com/flarum/cli/tree/3.x/boilerplate/skeleton/extension

composer.json, extend.php and README were then adapted to package the existing Telegram storage adapter. No site credentials were copied.

This project was generated using `flarum-cli init`; it was not copied using GitHub's “Use this template” button. The official extension boilerplate lives inside the `flarum/cli` repository linked above. CLI 3.x targets Flarum 2.x; `flarum/flarum` is the forum application skeleton, not this extension's template.
