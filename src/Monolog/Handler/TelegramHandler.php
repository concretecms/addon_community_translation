<?php

namespace CommunityTranslation\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Logger;

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
    const MAX_MESSAGE_LENGTH = 4096;

    /**
     * Telegram bot token.
     *
     * @var string
     */
    private $botToken;

    /**
     * Unique identifier for the target chat or username.
     *
     * @var string
     */
    private $chatID;

    /**
     * @param string $botToken Telegram bot token (you can create a new bot at https://telegram.me/botfather)
     * @param string|int $chatID Unique identifier for the target chat or username of the target channel (in the format @channelusername)
     * @param int $level The minimum logging level at which this handler will be triggered
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($botToken, $chatID, $level = Logger::CRITICAL, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->botToken = $botToken;
        $this->chatID = $chatID;
    }

    protected function safeHTML($text)
    {
        $result = (string) $text;
        $result = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', true);

        return $result;
    }

    /**
     * @param string $channel
     * @param string $message
     * @param int $maxLength
     *
     * @return string
     */
    private function buildMessageText($channel, $message, $maxLength = self::MAX_MESSAGE_LENGTH)
    {
        for (; ;) {
            $message = trim((string) $message);
            $result = '<b>' . $this->safeHTML($channel) . '</b>' . "\n" . '<pre>' . $this->safeHTML($message) . '</pre>';
            if ($message === '') {
                break;
            }
            $delta = mb_strlen($result) - $maxLength;
            if ($delta <= 0) {
                break;
            }
            $message = mb_substr($message, 0, max(0, mb_strlen($message) - $delta));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $url = sprintf(
            'https://api.telegram.org/bot%s/sendMessage',
            $this->botToken
        );

        $data = [
            'chat_id' => $this->chatID,
            'disable_web_page_preview' => true,
            'parse_mode' => 'HTML',
            'text' => $this->buildMessageText($record['channel'], $record['message']),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        Util::execute($ch);
    }
}
