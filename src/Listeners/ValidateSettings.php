<?php

namespace HideInMatrix\TelegramUpload\Listeners;

use Flarum\Foundation\ValidationException;
use Flarum\Settings\Event\Saving;
use HideInMatrix\TelegramUpload\Configuration;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ValidateSettings
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    public function handle(Saving $event): void
    {
        $methods = ['upload_url' => 'uploadUrl', 'chat_id' => 'chatId', 'api_key' => 'apiKey'];
        $errors = [];
        foreach ($methods as $name => $method) {
            $key = 'hideinmatrix-telegram-upload.'.$name;
            if (!array_key_exists($key, $event->settings)) {
                continue;
            }
            $value = $event->settings[$key];
            if (is_string($value) && $name !== 'api_key') {
                $value = trim($value);
                $event->settings[$key] = $value;
            }
            // Empty fields allow administrators to clear or configure the integration in stages.
            if ($value === '') {
                continue;
            }
            try {
                if (!is_string($value)) {
                    throw new InvalidArgumentException();
                }
                (new Configuration([$name => $value]))->$method();
            } catch (InvalidArgumentException) {
                $errors[$key] = $this->translator->trans('hideinmatrix-telegram-upload.admin.errors.'.$name);
            }
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
    }
}
