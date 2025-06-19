<?php

namespace Console\App\Service\PrestaShop;

class NightlyBoard
{
    private $jsonResults = [];

    /**
     * @param string $date
     * @param string $version
     * @param string $campaign
     * @param string $database
     *
     * @return array<string, int|string|array>
     */
    public function getReport(string $date, string $version, string $campaign, string $database): array
    {
        $json = $this->getReports();
        if (empty($json)) {
            return [];
        }
        $data = array_filter($json, function ($item) use ($date, $version, $campaign, $database) {
            return $item['date'] === $date
                && $item['version'] === $version
                && $item['campaign'] === $campaign
                && $item['database'] === $database;
        });

        return !empty($data) ? reset($data) : [];
    }

    /**
     * @param string $date
     * @param string $campaign
     *
     * @return array<string, int|string|array>
     */
    public function getCampaignReports(string $date, string $campaign): array
    {
        $json = $this->getReports();
        if (empty($json)) {
            return [];
        }
        $data = array_filter($json, function ($item) use ($date, $campaign) {
            return $item['date'] === $date && $item['campaign'] === $campaign;
        });

        return !empty($data) ? $data : [];
    }

    /**
     * @return array
     */
    protected function getReports(): array
    {
        if (!empty($this->jsonResults)) {
            return $this->jsonResults;
        }

        for ($page = 1; $page <= 10 ; $page++) {
            $reportsPage = $this->getReportsPage($page);

            $this->jsonResults = array_merge($this->jsonResults, $reportsPage);
        }

        return $this->jsonResults;
    }

    protected function getReportsPage(int $page = 1): array
    {
        $session = curl_init(sprintf(
            'https://api-nightly.prestashop-project.org/reports?limit=100&page=%d',
            $page
        ));
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $json = curl_exec($session);
        curl_close($session);

        $jsonArray = json_decode($json, true);
        return !empty($jsonArray) && !empty($jsonArray['reports']) ? $jsonArray['reports'] : [];
    }
}
