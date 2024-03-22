<?php

namespace Console\App\Service;

class Slack
{
    /**
     * @var string
     */
    protected $slackToken;

    public const MAINTAINER_MEMBERS = [
        'atomiix' => '<@UPBLRHWCU>',
        'eternoendless' => '<@U5GRYPEUC>',
        'jolelievre' => '<@UC4KB9BJS>',
        'kpodemski' => 'kpodemski',
        'matthieu-rolland' => '<@UKW0VAT8S>',
        'NeOMakinG' => '<@UQNF13DAR>',
        'PululuK' => 'PululuK',
        'sowbiba' => '<@USKJT4C4Q>',
    ];

    public const SOFTWARE_DEVELOPERS_IN_TEST = [
        'boubkerbribri' => '<@UGTRLG51N>',
        'cfarhani06' => '<@U03V3N293L7>',
        'Progi1984' => '<@UL16KUPC5>',
        'nesrineabdmouleh' => '<@U4YBPGMA8>',
        'SD1982' => '<@UTHRY2ZFY>',
    ];

    public const MAINTAINER_LEAD = 'eternoendless';

    public function __construct(string $slackToken = null)
    {
        $this->slackToken = $slackToken;
    }

    public function linkGithubUsername(string $message): string
    {
        $message = str_replace(array_keys(self::MAINTAINER_MEMBERS), array_values(self::MAINTAINER_MEMBERS), $message);
        $message = str_replace(array_keys(self::SOFTWARE_DEVELOPERS_IN_TEST), array_values(self::SOFTWARE_DEVELOPERS_IN_TEST), $message);

        return $message;
    }

    /**
     * @param string $channel
     * @param string $message
     */
    public function sendNotification(string $channel, string $message)
    {
        if (empty($message)) {
            return true;
        }
        $ch = \curl_init('https://slack.com/api/chat.postMessage');
        $data = http_build_query([
            'token' => $this->slackToken,
            'username' => 'PrestHubot',
            'channel' => $channel,
            'text' => $message,
            // Find and link channel names and usernames
            'link_names' => true,
            // Slack markup parsing
            'mrkdwn' => true,
            // Not unfurling of primarily text-based content
            'unfurl_links' => false,
            'unfurl_media' => false,
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
