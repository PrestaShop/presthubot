<?php

namespace Console\App\Service\PrestaShop;

class NightlyBoard
{
    private $jsonResults = '';

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
        $json = json_decode($json, true);
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
        $json = json_decode($json, true);
        $data = array_filter($json['reports'], function ($item) use ($date, $campaign) {
            return $item['date'] === $date && $item['campaign'] === $campaign;
        });

        return !empty($data) ? $data : [];
    }

    /**
     * @return string
     */
    protected function getReports(): string
    {
        if (!empty($this->jsonResults)) {
            return $this->jsonResults;
        }

        $session = curl_init('https://api-nightly.prestashop-project.org/reports');
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $this->jsonResults = curl_exec($session);
        curl_close($session);

        return $this->jsonResults;
    }
}
