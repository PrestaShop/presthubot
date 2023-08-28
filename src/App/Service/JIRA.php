<?php

namespace Console\App\Service;

class JIRA
{
    /**
     * @var int
     */
    protected const NUM_PER_PAGE = 50;

    /**
     * @var string
     */
    protected $jiraToken;

    public function __construct(string $jiraToken = null)
    {
        $this->jiraToken = $jiraToken;
    }

    public function findScenarios(?int $maxResults, bool $hasGithubPath, int $startAt = 0): array
    {
        $url = 'https://forge.prestashop.com/rest/api/2/search?jql=' . urlencode(
            'type = Test'
            . ' AND (issue in testRepositoryFolderTests(TEST, Core, "true") OR issue in testRepositoryFolderTests(TEST, Modules, "true"))'
            . ' AND status = "[Test] Automated"'
            . ' AND "Github Path" is ' . ($hasGithubPath ? 'NOT EMPTY' : 'EMPTY')
            . ' ORDER BY updated ASC'
        ) . '&maxResults=' . ($maxResults ?: self::NUM_PER_PAGE) . '&startAt=' . $startAt;
        $ch = curl_init();
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . $this->jiraToken,
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);
        $issues = $result['issues'] ?? [];

        if (!is_null($maxResults)) {
            return $issues;
        }

        $sumResult = $issues;

        do {
            $startAt += 50;

            $issues = $this->findScenarios(self::NUM_PER_PAGE, $hasGithubPath, $startAt);
            $sumResult = array_merge($sumResult, $issues);
        } while (count($issues) > 0);

        return $sumResult;
    }
}
