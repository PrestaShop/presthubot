<?php

namespace Console\App\Service;

class Slack
{
    /**
     * @var string
     */
    protected $slackToken;

    public function __construct(string $slackToken = null)
    {
        $this->slackToken = $slackToken;
    }

    /**
     * @param string $channel
     * @param string $message
     */
    public function sendNotification(string $channel, $message = 'test')
    {
        $ch = curl_init('https://slack.com/api/chat.postMessage');
        $data = http_build_query([
            'token' => $this->slackToken,
            'channel' => $channel,
            'text' => $message,
            'mrkdwn' => true,
            'username' => 'PrestHubot',
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
}
