<?php

namespace Console\App\Service\PrestaShop;

class NightlyBoard
{
    public function getReport(string $date, string $version): array
    {
        $json = $this->getReports();
        if (empty($json)) {
            return [];
        }
        $json = json_decode($json, true);
        $data = array_filter($json, function($item) use($date, $version) {
            return ($item['date'] == $date && $item['version'] == $version);
        });
        return !empty($data) ? reset($data) : [];
    }

    protected function getReports()
    {
        $session = curl_init('https://api-nightly.prestashop.com/reports');
        curl_setopt($session, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($session);
        curl_close($session);
        
        return $result;
    }
}
