<?php

declare(strict_types=1);

namespace CommunityTranslation\Monolog\Handler;

use Concrete\Core\Application\Application;
use Concrete\Core\Http\Client\Client;
use GuzzleHttp\Psr7\Request;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Sends notifications via Telegram.
 */
class TelegramHandler extends AbstractProcessingHandler
{
    /**
     * Maximum length of Telegram messages (up to 4096).
     *
     * @var int
     */
    private const MAX_MESSAGE_LENGTH = 4095;

    private Application $app;

    /**
     * Telegram bot token.
     */
    private string $botToken;

    /**
     * Unique identifier for the target chat or username.
     *
     * @var string|int
     */
    private $chatID;

    /**
     * @param string $botToken Telegram bot token (you can create a new bot at https://telegram.me/botfather)
     * @param string|int $chatID Unique identifier for the target chat or username of the target channel (in the format @channelusername)
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(Application $app, string $botToken, $chatID, int $level = Logger::CRITICAL, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->app = $app;
        $this->botToken = $botToken;
        $this->chatID = $chatID;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Monolog\Handler\AbstractProcessingHandler::write()
     */
    protected function write(array $record)
    {
        $data = [
            'chat_id' => $this->chatID,
            'disable_web_page_preview' => true,
            'parse_mode' => 'HTML',
        ];
        $this->setMessageText($data, $record['channel'] ?? '', $record['message'] ?? '');
        $request = new Request(
            'POST',
            "https://api.telegram.org/bot{$this->botToken}/sendMessage",
            [
                'Content-Type' => 'application/json',
            ],
            json_encode($data, JSON_THROW_ON_ERROR)
        );
        $client = $this->app->make(Client::class);
        $client->send($request);
    }

    private function setMessageText(array & $data, string $channel, string $message, int $maxLength = self::MAX_MESSAGE_LENGTH): void
    {
        $prefix = $channel === '' ? '' : "<b>{$this->safeHTML($channel)}</b>\n";
        for (;;) {
            $message = trim($message);
            $data['text'] = $prefix . '<pre>' . $this->safeHTML($message) . '</pre>';
            if ($message === '') {
                return;
            }
            $delta = strlen(json_encode($data, JSON_THROW_ON_ERROR)) - $maxLength;
            if ($delta <= 0) {
                return;
            }
            $message = mb_substr($message, 0, max(0, mb_strlen($message) - $delta));
        }
    }

    private function safeHTML(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8', true);
    }
}
