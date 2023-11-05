<?php

namespace App\Notify;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class TelegramNotifier
{
    protected string $token;

    protected int $chatId;

    public function __construct()
    {
        $this->token = getenv('TELEGRAM_TOKEN');
        $this->chatId = (int) getenv('TELEGRAM_CHAT_ID');
    }

    public function notify($text)
    {

        $client = new Client([
            "base_uri" => "https://api.telegram.org",
        ]);
        try {
            //$response = $client->request("GET", "/bot$this->token/getUpdates");
            //
            //var_dump($response->getBody()->getContents());die;


            $url = sprintf('/bot%s/sendMessage', $this->token);

            $client->post($url, [

                RequestOptions::JSON => [

                    'chat_id' => $this->chatId,

                    'text' => $text,

                ]

            ]);

        } catch (\Exception $e) {

            var_dump($e->getMessage());

        }

    }
}