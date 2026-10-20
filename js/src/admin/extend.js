import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const translation = (key) => app.translator.trans(`hideinmatrix-telegram-upload.admin.${key}`, {}, true);

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'hideinmatrix-telegram-upload.upload_url',
      label: translation('upload_url_label'),
      help: translation('upload_url_help'),
      type: 'url',
      placeholder: 'https://your-tg-disk.example.com/api/telegram/send',
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
