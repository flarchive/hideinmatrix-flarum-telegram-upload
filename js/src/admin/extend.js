import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const translation = (key) => app.translator.trans(`hideinmatrix-telegram-upload.admin.${key}`, {}, true);

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'hideinmatrix-telegram-upload.base_url',
      label: translation('base_url_label'),
      help: translation('base_url_help'),
      type: 'url',
      placeholder: 'https://your-tg-disk.example.com',
    }), 40)
    .setting(() => ({
      setting: 'hideinmatrix-telegram-upload.provider',
      label: translation('provider_label'),
      help: translation('provider_help'),
      type: 'select',
      options: { telegram: 'Telegram', r2: 'Cloudflare R2' },
      default: 'telegram',
    }), 30)
    .setting(() => ({
      setting: 'hideinmatrix-telegram-upload.api_key',
      label: 'TG_DISK_API_KEY',
      help: translation('api_key_help'),
      type: 'password',
      autocomplete: 'new-password',
    }), 20)
    .setting(() => ({
      setting: 'hideinmatrix-telegram-upload.chat_id',
      label: translation('chat_id_label'),
      help: translation('chat_id_help'),
      type: 'text',
      placeholder: '-1000000000000',
    }), 10),
];
