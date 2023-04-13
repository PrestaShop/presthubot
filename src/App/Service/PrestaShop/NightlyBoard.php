<?php

namespace Console\App\Service\PrestaShop;

class NightlyBoard
{
    /**
     * @param string $date
     * @param string $version
     * @param string $campaign
     *
     * @return array<string, int|string|array>
     */
    public function getReport(string $date, string $version, string $campaign): array
    {
        $json = $this->getReports();
        if (empty($json)) {
            return [];
        }
        $json = json_decode($json, true);
        $data = array_filter($json, function ($item) use ($date, $version, $campaign) {
            return $item['date'] === $date
                && $item['version'] === $version
                && $item['campaign'] === $campaign;
        });

        return !empty($data) ? reset($data) : [];
    }

    /**
     * @return string
     */
    protected function getReports(): string
    {
        $session = curl_init('https://api-nightly.prestashop-project.org/reports');
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($session);
        curl_close($session);

        return $result;
    }
}
