<?php
namespace Console\App\Command;

use Console\App\Command\GithubCheckModuleCommand;
use Console\App\Service\Github;
use Console\App\Service\Github\Filters;
use Console\App\Service\Github\Query;
use Console\App\Service\PrestaShop\ModuleChecker;
use Console\App\Service\PrestaShop\NightlyBoard;
use Console\App\Service\Slack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
class SlackNotifierCommand extends Command
{
    /**
     * @var Github;
     */
    protected $github;
    /**
     * @var ModuleChecker;
     */
    protected $moduleChecker;
    /**
     * @var NightlyBoard;
     */
    protected $nightlyBoard;
    /**
     * @var Slack;
     */
    protected $slack;
    /**
     * @var string;
     */
    protected $slackChannel;

    protected function configure()
    {
        $this->setName('slack:notifier')
            ->setDescription('Check Github Module')
            ->addOption(
                'ghtoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['GH_TOKEN'] ?? null
            )
            ->addOption(
                'slacktoken',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['SLACK_TOKEN'] ?? null
            )
            ->addOption(
                'slackchannel',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['SLACK_CHANNEL'] ?? null
            );   
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Local Variable
        $slackMessage = [];

        $this->github = new Github($input->getOption('ghtoken'));
        $this->moduleChecker = new ModuleChecker($this->github);
        $this->nightlyBoard = new NightlyBoard();
        $this->slack = new Slack($input->getOption('slacktoken'));
        $this->slackChannel = $input->getOption('slackchannel');
        $slackMessage[] = ':preston::date: Welcome to the PrestHubot Report of the day :date:';
        
        // Check Status
        $slackMessage[] = $this->checkStatusNightly();

        // Check if PR are need to merge
        $slackMessage[] = $this->checkPRReadyToMerge();

        // Check PR to review
        $slackMessage[] = $this->checkPRReadyToReview();

        // Need module releases
        $slackMessage[] = $this->checkModuleReadyToRelease();

        // Need module improvements
        $slackMessage[] = $this->checkModuleImprovements();

        foreach ($slackMessage as $message) {
            if (!empty($message)) {
                $this->slack->sendNotification($this->slackChannel, $message);
            }
        }
    }

    protected function checkStatusNightly(): string
    {
        $report177x = $this->nightlyBoard->getReport(date('Y-m-d'), '1.7.7.x');
        $reportDevelop = $this->nightlyBoard->getReport(date('Y-m-d'), 'develop');

        $has177XPassed = isset($report177x['tests'], $report177x['tests']['passed']);
        $has177XFailed = isset($report177x['tests'], $report177x['tests']['failed']);
        $has177XPending = isset($report177x['tests'], $report177x['tests']['pending']);
        $hasDevelopPassed = isset($reportDevelop['tests'], $reportDevelop['tests']['passed']);
        $hasDevelopFailed = isset($reportDevelop['tests'], $reportDevelop['tests']['failed']);
        $hasDevelopPending = isset($reportDevelop['tests'], $reportDevelop['tests']['pending']);
        
        $status177X = ($has177XFailed && $report177x['tests']['failed'] == 0);
        $statusDevelop = ($hasDevelopFailed && $reportDevelop['tests']['failed'] == 0);
        
        $emoji177X = $status177X ? ':greenlight:' : ':redlight:';
        $emojiDevelop = $statusDevelop ? ':greenlight:' : ':redlight:';
        
        $slackMessage = ':notebook_with_decorative_cover: Nightly Board :notebook_with_decorative_cover:' . PHP_EOL;
        $slackMessage .= ' - <https://nightly.prestashop.com/report/'.$report177x['id'].'|'.$emoji177X.' Report `1.7.7.x`>';
        $slackMessage .= ' : ';
        $slackMessage .= $has177XPassed ? ':heavy_check_mark: ' . $report177x['tests']['passed'] : '';
        $slackMessage .= ($has177XPassed && ($has177XFailed || $has177XPending) ? ' - ' : '');
        $slackMessage .= $has177XFailed ? ':x: ' . $report177x['tests']['failed'] : '';
        $slackMessage .= (($has177XPassed || $has177XFailed) && ($has177XPending) ? ' - ' : '');
        $slackMessage .= $has177XPending ? '⏸️ ' . $report177x['tests']['pending'] : '';
        $slackMessage .= PHP_EOL;
        $slackMessage .= ' - <https://nightly.prestashop.com/report/'.$reportDevelop['id'].'|'.$emojiDevelop.' Report `develop`>';
        $slackMessage .= ' : ';
        $slackMessage .= $hasDevelopPassed ? ':heavy_check_mark: ' . $reportDevelop['tests']['passed'] : '';
        $slackMessage .= ($hasDevelopPassed && ($hasDevelopFailed || $hasDevelopPending) ? ' - ' : '');
        $slackMessage .= $hasDevelopFailed ? ':x: ' . $reportDevelop['tests']['failed'] : '';
        $slackMessage .= (($hasDevelopPassed || $hasDevelopFailed) && ($hasDevelopPending) ? ' - ' : '');
        $slackMessage .= $hasDevelopPending ? '⏸️ ' . $reportDevelop['tests']['pending'] : '';
        $slackMessage .= PHP_EOL;
        return $slackMessage;
    }

    protected function checkPRReadyToMerge(): string
    {
        $requests = Query::getRequests();
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr ' . $requests[Query::REQUEST_PR_WAITING_FOR_MERGE]);
        $prReadyToMerge = $this->github->search($graphQLQuery);
        if (!empty($prReadyToMerge)) {
            $slackMessage = ':rocket: PR Ready to Merge :rocket:' . PHP_EOL;
            foreach ($prReadyToMerge as $pullRequest) {
                $pullRequest = $pullRequest['node'];
                $slackMessage .= ' - <'.$pullRequest['url'].'|:preston: '.$pullRequest['repository']['name'].'#'.$pullRequest['number'] .'>'
                    .' : '.$pullRequest['title'] . PHP_EOL;
            }
            return $slackMessage;
        }
        return '';
    }

    protected function checkPRReadyToReview(): string
    {
        $requests = Query::getRequests();
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr ' . $requests[Query::REQUEST_PR_WAITING_FOR_REVIEW]);
        $prReviews = $this->github->search($graphQLQuery);
        $prReadyToReview = [];
        $filters = new Filters();
        $filters->addFilter(Filters::FILTER_REPOSITORY_PRIVATE, [false], true);
        // 1st PR with already a review (indicate who has ever)
        // 2nd PR without review
        foreach ([5,4,3,2,1,0] as $numApproved) {
            $filters->addFilter(Filters::FILTER_NUM_APPROVED, [$numApproved], true);
            foreach ($prReviews as $pullRequest) {
                $pullRequest = $pullRequest['node'];
                $pullRequest['approved'] = $this->github->extractApproved($pullRequest);
                if (!$this->github->isPRValid($pullRequest, $filters)) {
                    continue;
                }
                $prReadyToReview[] = $pullRequest;
            }
            if (count($prReadyToReview) >= 10) {
                break;
            }
        }
        if (!empty($prReadyToReview)) {
            $prReadyToReview = array_slice($prReadyToReview, 0, 10);
            $slackMessage = ':eyes: PR Ready to Review :eyes:' . PHP_EOL;
            foreach ($prReadyToReview as $pullRequest) {
                $slackMessage .= ' - <'.$pullRequest['url'].'|:preston: '.$pullRequest['repository']['name'].'#'.$pullRequest['number'] .'>'
                    .' : '.$pullRequest['title'];
                if (!empty($pullRequest['approved'])) {
                    $slackMessage .= PHP_EOL;
                    $slackMessage .= '    - :heavy_check_mark: ' . implode(', ', $pullRequest['approved']);
                }
                $slackMessage .= PHP_EOL;
                $slackMessage .= PHP_EOL;
            }
            $slackMessage = $this->slack->linkGithubUsername($slackMessage);
            return $slackMessage;
        }
        return '';
    }

    protected function checkModuleReadyToRelease(): string
    {
        $modulesNeedRelease = [];
        foreach (GithubCheckModuleCommand::REPOSITORIES as $repository) {
            $checkBranches = $this->moduleChecker->checkBranches('PrestaShop', $repository);
            if ($checkBranches['hasDiffMaster']) {
                $modulesNeedRelease[$repository] = $checkBranches['status'];
            }
        }
        uasort($modulesNeedRelease, function($a, $b) {
            if ($a['numPRMerged'] == $b['numPRMerged']) {
                if ($a['ahead'] == $b['ahead']) {
                    return 0;
                }
                return ($a['ahead'] > $b['ahead']) ? -1 : 1;
            }
            return ($a['numPRMerged'] > $b['numPRMerged']) ? -1 : 1;
        });
        if (!empty($modulesNeedRelease)) {
            $modulesNeedRelease = array_slice($modulesNeedRelease, 0, 10);
            $slackMessage = ':rocket: Modules need some release :rocket:' . PHP_EOL;
            foreach ($modulesNeedRelease as $repository => $status) {
                $slackMessage .= ' - <https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> ';
                $slackMessage .= '('.$status['ahead'].' commits / '.$status['numPRMerged'].' PR'.($status['numPRMerged'] > 1 ? 's': '').')';
                $slackMessage .= PHP_EOL;
            }
            return $slackMessage;
        }
        return '';
    }

    protected function checkModuleImprovements(): string
    {
        $improvements = [];
        foreach (GithubCheckModuleCommand::REPOSITORIES as $repository) {
            if (count($improvements) > 10) {
                break;
            }
            $this->moduleChecker->resetChecker();
            $this->moduleChecker->checkRepository('PrestaShop', $repository);
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_GLOBAL) == ModuleChecker::RATING_GLOBAL_MAX) {
                continue;
            }
            $report = $this->moduleChecker->getReport();
            if ($report['archived'] || $report['moved']) {
                $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Please remove for the Presthubot analysis';
                continue;
            }
            
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_BRANCH) != ModuleChecker::RATING_BRANCH_MAX) {
                // Needs release (cf. checkModuleReadyToRelease)
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_DESCRIPTION) != ModuleChecker::RATING_DESCRIPTION_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Please description ' . $path;
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_FILES) != ModuleChecker::RATING_FILES_MAX) {
                foreach ($report['files'] as $path => $check) {
                    $allCheck = true;
                    foreach ($check as $key => $value) {
                        $allCheck = $allCheck && $value;
                    }
                    if ($allCheck) {
                        continue;
                    }
                    if (!$check[ModuleChecker::CHECK_FILES_EXIST]) {
                        $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Missing file ' . $path;
                    }
                    if (isset($check[ModuleChecker::CHECK_FILES_CONTAIN])) {
                        $remainingCheck = true;
                        foreach ($check as $key => $value) {
                            if ($check == ModuleChecker::CHECK_FILES_EXIST) {
                                continue;
                            }
                            $remainingCheck = $remainingCheck && $value;
                        }
                        if (!$remainingCheck) {
                            $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Invalid file ' . $path;
                        }
                    }
                }
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_ISSUES) != ModuleChecker::RATING_ISSUES_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Please migrate issues to main repository and close issues on the module repository';
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_LABELS) != ModuleChecker::RATING_LABELS_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Please fix labels ' . $path;
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_LICENSE) != ModuleChecker::RATING_LICENSE_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Invalid license (Check composer.json)';
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_TOPICS) != ModuleChecker::RATING_TOPICS_MAX) {
                $topics = '';
                foreach ($report['githubTopics'] as $key => $value) {
                    if (!$value) {
                        $topics .= (empty($topics) ? $key : ', ' . $key);
                    }
                }
                $improvements[] = '<https://github.com/PrestaShop/'.$repository.'|:preston: '.$repository.'> Please add missings topics on Github (' . $topics . ')';
            }
        }
        if (!empty($improvements)) {
            $improvements = array_slice($improvements, 0, 10);
            $slackMessage = ':pencil: Modules need some improvements :pencil:' . PHP_EOL;
            foreach ($improvements as $message) {
                $slackMessage .= ' - ' . $message .' (on master branch)' . PHP_EOL;
            }
            return $slackMessage;
        }
        return '';
    }
}
