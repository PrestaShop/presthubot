<?php

use Console\App\Service\Github;
use Console\App\Service\PrestaShop\ModuleFetcher;
use Github\Api\Repo;
use Github\Api\Repository\Contents;
use Github\Client;
use PHPUnit\Framework\TestCase;

class ModuleFetcherTest extends TestCase
{
    /**
     * @dataProvider getCases
     */
    public function testGetModules(array $apiReturn, array $expected)
    {
        $moduleFetcher = new ModuleFetcher($this->getMockGithub($apiReturn));
        $modules = $moduleFetcher->getModules();

        $this->assertEquals($expected, $modules);
    }

    private function getMockGithub(array $returnData): Github
    {
        $mockContents = $this->getMockBuilder(Contents::class)->disableOriginalConstructor()->getMock();
        $mockContents->method('show')->willReturn($returnData);

        $mockRepo = $this->getMockBuilder(Repo::class)->disableOriginalConstructor()->getMock();
        $mockRepo->method('contents')->willReturn($mockContents);

        $mockClient = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->getMock();
        $mockClient->method('api')->willReturn($mockRepo);

        $mockGithub = $this->getMockBuilder(Github::class)->disableOriginalConstructor()->getMock();
        $mockGithub->method('getClient')->willReturn($mockClient);

        return $mockGithub;
    }

    public function getCases()
    {
        yield [
            [['download_url' => '', 'name' => 'TestA']],
            ['TestA'],
        ];
        yield [
            [['download_url' => 'https://', 'name' => 'TestB']],
            [],
        ];
    }
}
