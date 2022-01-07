<?php

namespace Console\App\Command;

use Console\App\Service\Github;
use Console\App\Service\Github\Filters;
use Console\App\Service\Github\Query;
use Console\App\Service\PrestaShop\ModuleChecker;
use Console\App\Service\PrestaShop\ModuleFetcher;
use Console\App\Service\PrestaShop\NightlyBoard;
use Console\App\Service\Slack;
use Symfony\Component\Console\Command\Command;
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
     * @var ModuleFetcher
     */
    protected $moduleFetcher;
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
    protected $slackChannelCore;
    /**
     * @var string;
     */
    protected $slackChannelQA;

    /**
     * @var int
     */
    private const NUM_PR_FOR_MAINTAINERS = 5;

    /**
     * @var string
     */
    private const ERROR_INVALID_CATEGORY = 'Invalid category';

    /**
     * @var string
     */
    private const ERROR_INVALID_TYPE = 'Invalid type';

    /**
     * @var string
     */
    private const ERROR_TITLE_FORMAT = 'Pull Request title does not start with an uppercase letter';

    /**
     * @var string
     */
    private const ERROR_NO_MILESTONE = 'No milestone defined';

    /**
     * @var array<string,string>
     */
    private const ACCEPTED_CATEGORIES = [
        'FO' => 'Front office',
        'CO' => 'Core',
        'BO' => 'Back office',
        'WS' => 'Web services',
        'IN' => 'Installer',
        'TE' => 'Tests',
        'LO' => 'Localization',
        'ME' => 'Merge',
        'PM' => 'Project management',
    ];

    /**
     * @var array<string>
     */
    private const ACCEPTED_TYPES = [
        'bug fix',
        'improvement',
        'refacto',
        'new feature',
    ];

    /**
     * @var array<string>
     */
    private const BRANCH_SUPPORT = [
        '1.7.8.x',
        'develop',
    ];

    protected function configure()
    {
        $this->setName('slack:notifier')
            ->setDescription('Notify Teams on Slack every day')
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
                'slackchannelCore',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['SLACK_CHANNEL_CORE'] ?? null
            )
            ->addOption(
                'slackchannelQA',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['SLACK_CHANNEL_QA'] ?? null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Local Variable
        $slackMessageCore = $slackMessageQA = $slackMessageCoreMembers = [];

        $this->github = new Github($input->getOption('ghtoken'));
        $this->moduleChecker = new ModuleChecker($this->github);
        $this->moduleFetcher = new ModuleFetcher($this->github);
        $this->nightlyBoard = new NightlyBoard();
        $this->slack = new Slack($input->getOption('slacktoken'));
        $this->slackChannelCore = $input->getOption('slackchannelCore');
        $this->slackChannelQA = $input->getOption('slackchannelQA');

        $title = ':preston::date: Welcome to the PrestHubot Report of the day :date:';
        $slackMessageCore[] = $title;
        $slackMessageQA[] = $title;

        // Check Status
        $statusNightly = $this->checkStatusNightly();
        $slackMessageCore[] = $statusNightly;
        $slackMessageQA[] = $statusNightly;

        // Check if PR are need to merge
        $slackMessageCore[] = $this->checkPRReadyToMerge();

        // Check PR to review
        $slackMessageCore[] = $this->checkPRReadyToReview();

        // Need module releases
        $slackMessageCore[] = $this->checkModuleReadyToRelease();

        // Need module improvements
        $slackMessageCore[] = $this->checkModuleImprovements();

        // Check QA Stats
        $slackMessageQA[] = $this->checkStatsQA();

        // Check PR Priority to Test
        $prReadyToTest = $this->checkPRReadyToTest();
        $slackMessageQA[] = $prReadyToTest;
        $slackMessageCore[] = $prReadyToTest;

        // Get PR to Review for Core Team
        $slackMessageCoreMembers[] = $this->checkPRReadyToReviewForCoreTeam();

        // Get PR to Check Naming for CoreTeam
        $slackMessageCoreMembers[] = $this->checkPRNaming();

        // Send Message to Merge to Develop for CoreTeam
        $slackMessageCoreMembers[] = $this->needMergeToDevelop();

        foreach ($slackMessageCore as $message) {
            $this->slack->sendNotification($this->slackChannelCore, $message);
        }
        foreach ($slackMessageQA as $message) {
            $this->slack->sendNotification($this->slackChannelQA, $message);
        }
        foreach ($slackMessageCoreMembers as $messages) {
            foreach ($messages as $slackChannelPrivateMaintainer => $message) {
                $this->slack->sendNotification($slackChannelPrivateMaintainer, $message);
            }
        }

        return 0;
    }

    protected function checkStatusNightly(): string
    {
        $slackMessage = ':notebook_with_decorative_cover: Nightly Board :notebook_with_decorative_cover:' . PHP_EOL;

        foreach (self::BRANCH_SUPPORT as $branch) {
            $report = $this->nightlyBoard->getReport(date('Y-m-d'), $branch, 'functional');
            $hasPassed = isset($report['tests'], $report['tests']['passed']);
            $hasFailed = isset($report['tests'], $report['tests']['failed']);
            $hasPending = isset($report['tests'], $report['tests']['pending']);
            $status = ($hasFailed && $report['tests']['failed'] == 0);
            $emoji = $status ? ':greenlight:' : ':redlight:';

            $slackMessage .= ' - <https://nightly.prestashop.com/report/' . $report['id'] . '|' . $emoji . ' Report `' . $branch . '`>';
            $slackMessage .= ' : ';
            $slackMessage .= $hasPassed ? ':heavy_check_mark: ' . $report['tests']['passed'] : '';
            $slackMessage .= ($hasPassed && ($hasFailed || $hasPending) ? ' - ' : '');
            $slackMessage .= $hasFailed ? ':x: ' . $report['tests']['failed'] : '';
            $slackMessage .= (($hasPassed || $hasFailed) && ($hasPending) ? ' - ' : '');
            $slackMessage .= $hasPending ? '⏸️ ' . $report['tests']['pending'] : '';
            $slackMessage .= PHP_EOL;
        }

        return $slackMessage;
    }

    protected function checkPRNaming(): array
    {
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('repo:PrestaShop/PrestaShop is:pr is:merged sort:created');
        $arrayPullRequest = $this->github->search($graphQLQuery);

        $arrayTeamPR = [];
        foreach (Slack::MAINTAINER_MEMBERS as $key => $value) {
            if ($key == $value) {
                continue;
            }
            $arrayTeamPR[$key] = [];
        }
        unset($arrayTeamPR[Slack::MAINTAINER_LEAD]);

        $buildPattern = '/^(?:\\s*\\|?\\s*)%propertyName%\\??\\s*\\|\\s*(?%captureGroup%)(?:\\s*\\|?\\s*)$/im';
        foreach ($arrayPullRequest as $pullRequest) {
            $pullRequest = $pullRequest['node'];
            // Category
            $category = '';
            if (preg_match(
                    str_replace(['%propertyName%', '%captureGroup%'], ['Category', '<category>[a-z]{2}'], $buildPattern),
                    $pullRequest['body'],
                    $matches
            )) {
                $category = trim(strtoupper($matches['category']));
            }
            // Type
            $type = '';
            if (preg_match(
                    str_replace(['%propertyName%', '%captureGroup%'], ['Type', '<type>[a-zA-Z\\s]+'], $buildPattern),
                    $pullRequest['body'],
                    $matches
            )) {
                $type = trim(strtolower($matches['type']));
            }

            // Search errors
            $errors = [];
            if (!in_array($category, array_keys(self::ACCEPTED_CATEGORIES))) {
                $errors[] = self::ERROR_INVALID_CATEGORY;
            }
            if (!in_array($type, self::ACCEPTED_TYPES)) {
                $errors[] = self::ERROR_INVALID_TYPE;
            }
            if (preg_match('/^[^A-Z]/', $pullRequest['title'])) {
                $errors[] = self::ERROR_TITLE_FORMAT;
            }
            if (empty($pullRequest['milestone'])) {
                $errors[] = self::ERROR_NO_MILESTONE;
            }
            if (empty($errors)) {
                continue;
            }
            foreach (array_keys($arrayTeamPR) as $maintainer) {
                // Has the maintainer already self::NUM_PR_FOR_MAINTAINERS PR ?
                if (count($arrayTeamPR[$maintainer]) == self::NUM_PR_FOR_MAINTAINERS) {
                    continue;
                }
                $slackMessage = ' - <' . $pullRequest['url'] . '|:preston: ' . $pullRequest['repository']['name'] . '#' . $pullRequest['number'] . '>'
                    . ' : ' . $pullRequest['title'] . PHP_EOL;
                foreach ($errors as $error) {
                    $slackMessage .= '    - :red_circle: ' . $error . PHP_EOL;
                }
                $slackMessage .= PHP_EOL;
                $slackMessage .= PHP_EOL;
                $arrayTeamPR[$maintainer][] = $slackMessage;
                break;
            }
        }

        // Slack Messages
        $arrayMessage = [];
        $slackMessageTitle = ':pray: Could you fix these PRs ? :pray:' . PHP_EOL;
        foreach ($arrayTeamPR as $maintainer => $messages) {
            if (empty($messages)) {
                continue;
            }
            $slackMessage = $slackMessageTitle;
            foreach ($messages as $message) {
                $slackMessage .= $message;
            }
            $slackMessage = $this->slack->linkGithubUsername($slackMessage);
            $slackChannel = Slack::MAINTAINER_MEMBERS[$maintainer];
            $slackChannel = str_replace(['<@', '>'], '', $slackChannel);

            $arrayMessage[$slackChannel] = $slackMessage;
        }

        return $arrayMessage;
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
                $slackMessage .= ' - <' . $pullRequest['url'] . '|:preston: ' . $pullRequest['repository']['name'] . '#' . $pullRequest['number'] . '>'
                    . ' : ' . $pullRequest['title'] . PHP_EOL;
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
        foreach ([5, 4, 3, 2, 1, 0] as $numApproved) {
            $filters->addFilter(Filters::FILTER_NUM_APPROVED, [$numApproved], true);
            foreach ($prReviews as $pullRequest) {
                $pullRequest = $pullRequest['node'];
                $pullRequest['approved'] = $this->github->extractPullRequestState($pullRequest, Github::PULL_REQUEST_STATE_APPROVED);
                $pullRequest['request_changes'] = $this->github->extractPullRequestState($pullRequest, Github::PULL_REQUEST_STATE_REQUEST_CHANGES);
                $pullRequest['comment'] = $this->github->extractPullRequestState($pullRequest, Github::PULL_REQUEST_STATE_COMMENT);
                if (!$this->github->isPRValid($pullRequest, $filters)) {
                    continue;
                }
                $prReadyToReview[] = $pullRequest;
            }
            if (count($prReadyToReview) >= 10) {
                break;
            }
        }
        if (empty($prReadyToReview)) {
            return '';
        }
        $prReadyToReview = array_slice($prReadyToReview, 0, 10);
        $slackMessage = ':eyes: PR Ready to Review :eyes:' . PHP_EOL;
        foreach ($prReadyToReview as $pullRequest) {
            $slackMessage .= ' - <' . $pullRequest['url'] . '|:preston: ' . $pullRequest['repository']['name'] . '#' . $pullRequest['number'] . '>'
                . ' : ' . $pullRequest['title'];
            if (!empty($pullRequest['approved'])) {
                $slackMessage .= PHP_EOL;
                $slackMessage .= '    - :heavy_check_mark: ' . implode(', ', $pullRequest['approved']);
            }
            if (!empty($pullRequest['request_changes'])) {
                $slackMessage .= PHP_EOL;
                $slackMessage .= '    - :no_entry: ' . implode(', ', $pullRequest['request_changes']);
            }
            if (!empty($pullRequest['comment'])) {
                $slackMessage .= PHP_EOL;
                $slackMessage .= '    - :thought_balloon: ' . implode(', ', $pullRequest['comment']);
            }
            $slackMessage .= PHP_EOL;
            $slackMessage .= PHP_EOL;
        }
        $slackMessage = $this->slack->linkGithubUsername($slackMessage);

        return $slackMessage;
    }

    protected function checkPRReadyToReviewForCoreTeam(): array
    {
        $requests = Query::getRequests();
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr ' . $requests[Query::REQUEST_PR_WAITING_FOR_REVIEW]);
        $prReviews = $this->github->search($graphQLQuery);
        $prReadyToReview = [];
        $filters = new Filters();
        $filters->addFilter(Filters::FILTER_REPOSITORY_PRIVATE, [false], true);
        $filters->addFilter(Filters::FILTER_REPOSITORY_NAME, [
            'prestashop-specs',
        ], false);
        // 1st PR with already a review (indicate who has ever)
        // 2nd PR without review
        foreach ([5, 4, 3, 2, 1, 0] as $numApproved) {
            $filters->addFilter(Filters::FILTER_NUM_APPROVED, [$numApproved], true);
            foreach ($prReviews as $pullRequest) {
                $pullRequest = $pullRequest['node'];
                $pullRequest['approved'] = $this->github->extractPullRequestState($pullRequest, Github::PULL_REQUEST_STATE_APPROVED);
                if (!$this->github->isPRValid($pullRequest, $filters)) {
                    continue;
                }
                $prReadyToReview[] = $pullRequest;
            }
        }
        if (empty($prReadyToReview)) {
            return [];
        }

        $arrayTeamPR = [];
        foreach (Slack::MAINTAINER_MEMBERS as $key => $value) {
            if ($key == $value) {
                continue;
            }
            $arrayTeamPR[$key] = [];
        }
        unset($arrayTeamPR[Slack::MAINTAINER_LEAD]);
        unset($arrayTeamPR['PierreRambaud']);

        // Check PR for each
        foreach ($prReadyToReview as $pullRequest) {
            // Add PR to two maintainers
            $isAdded = 0;
            foreach (array_keys($arrayTeamPR) as $maintainer) {
                // Has the maintainer already self::NUM_PR_FOR_MAINTAINERS PR ?
                if (count($arrayTeamPR[$maintainer]) == self::NUM_PR_FOR_MAINTAINERS) {
                    continue;
                }
                // Is the maintainer the author ?
                if ($maintainer === $pullRequest['author']['login']) {
                    continue;
                }
                // Has the maintainer already approved ?
                if (in_array($maintainer, $pullRequest['approved'])) {
                    continue;
                }
                $arrayTeamPR[$maintainer][] = $pullRequest;
                ++$isAdded;

                if ($isAdded == 2) {
                    break;
                }
            }

            // Check PR For maintainers
            $isFullForMaintainers = array_reduce($arrayTeamPR, function ($carry, $item) {
                if (!$carry) {
                    return false;
                }

                foreach ($item as $value) {
                    if (count($value) < self::NUM_PR_FOR_MAINTAINERS) {
                        return false;
                    }
                }

                return true;
            }, false);
            if ($isFullForMaintainers) {
                break;
            }
        }

        // Slack Messages
        $arrayMessage = [];
        $slackMessageTitle = ':pray: Could you review these PRs ? :pray:' . PHP_EOL;
        foreach ($arrayTeamPR as $maintainer => $arrayPullRequest) {
            $slackMessage = $slackMessageTitle;
            foreach ($arrayPullRequest as $pullRequest) {
                $slackMessage .= ' - <' . $pullRequest['url'] . '|:preston: ' . $pullRequest['repository']['name'] . '#' . $pullRequest['number'] . '>'
                    . ' : ' . $pullRequest['title'];
                if (!empty($pullRequest['approved'])) {
                    $slackMessage .= PHP_EOL;
                    $slackMessage .= '    - :heavy_check_mark: ' . implode(', ', $pullRequest['approved']);
                }
                $slackMessage .= PHP_EOL;
                $slackMessage .= PHP_EOL;
            }
            $slackMessage = $this->slack->linkGithubUsername($slackMessage);
            $slackChannel = Slack::MAINTAINER_MEMBERS[$maintainer];
            $slackChannel = str_replace(['<@', '>'], '', $slackChannel);

            $arrayMessage[$slackChannel] = $slackMessage;
        }

        return $arrayMessage;
    }

    protected function checkPRReadyToTest(): string
    {
        // Next version
        $content = $this->github->getClient()->api('repo')->contents()->download('PrestaShop', 'PrestaShop', 'app/AppKernel.php', 'refs/heads/develop');
        preg_match("#const VERSION = \'([0-9\.]+)\'#", $content, $matches);
        $nextVersion = $matches[1] ?? 'develop';

        $requests = Query::getRequests();
        $graphQLQuery = new Query();
        $graphQLQuery->setQuery('org:PrestaShop is:pr ' . $requests[Query::REQUEST_PR_WAITING_FOR_QA]);
        $results = $this->github->search($graphQLQuery);
        foreach ($results as $key => &$result) {
            if ($result['node']['repository']['name'] == 'prestashop-specs') {
                unset($results[$key]);
                continue;
            }
            // Issue
            $result['linkedIssue'] = $this->github->getLinkedIssue($result['node']);
            // Labels
            $issueLabels = array_map(function ($value) {
                return $value['name'];
            }, is_array($result['linkedIssue']['labels']) ? $result['linkedIssue']['labels'] : []);
            // Priority
            $result['priority'] = array_reduce($issueLabels, function ($carry, $item) {
                if ($carry < 2 && $item === 'Must-have') {
                    return 2;
                }
                if ($carry < 1 && $item === 'Nice-to-have') {
                    return 1;
                }

                return $carry;
            }, 0);
            // Milestone
            $result['milestone'] = '';
            if ($result['node']['repository']['name'] == 'PrestaShop') {
                $result['milestone'] = $result['node']['milestone'];
                $result['milestone'] = !empty($result['milestone']) ? $result['milestone']['title'] : $nextVersion;
            }
        }

        // Sort PR
        usort($results, function ($a, $b) {
            $aRepoName = $a['node']['repository']['name'];
            $bRepoName = $b['node']['repository']['name'];
            $aMilestone = $a['node']['milestone'];
            $aMilestone = $a['milestone'];
            $bMilestone = $b['milestone'];
            $aCreatedAt = $a['node']['createdAt'];
            $bCreatedAt = $b['node']['createdAt'];
            $aLinkedIssue = $a['linkedIssue'];
            $bLinkedIssue = $b['linkedIssue'];
            $aPriority = $a['priority'];
            $bPriority = $b['priority'];

            // #1 : Core, Modules
            if ($aRepoName === 'PrestaShop' && $bRepoName !== 'PrestaShop') {
                return -1;
            }
            if ($bRepoName === 'PrestaShop' && $aRepoName !== 'PrestaShop') {
                return 1;
            }
            if ($aRepoName === 'PrestaShop') {
                // #2 : Milestone
                if ($aMilestone !== $bMilestone) {
                    return ($aMilestone < $bMilestone) ? -1 : 1;
                }
                // #3 : Must Have / Nice-to-have / etc...
                if ($aPriority !== $bPriority) {
                    return ($aPriority > $bPriority) ? -1 : 1;
                }
            }
            // #4 : old to new
            return ($aCreatedAt < $bCreatedAt) ? -1 : 1;
        });

        // Extract PR per milestone
        $slackMessage = ':eyes: PR Ready to Test *(' . count($results) . ')* :eyes:' . PHP_EOL;
        $milestone = null;
        $milestoneCount = 0;
        foreach ($results as $key => $pullRequest) {
            if (empty($milestone)) {
                $milestone = $pullRequest['milestone'];
            }
            if ($milestone !== $pullRequest['milestone'] || $milestoneCount == 3) {
                if ($milestone == $pullRequest['milestone']) {
                    continue;
                }
                $milestone = $pullRequest['milestone'];
                $milestoneCount = 0;
                $slackMessage .= PHP_EOL;
            }

            $slackMessage .= ' - '
                . '*[' . (empty($pullRequest['milestone']) ? 'Modules' : $pullRequest['milestone']) . ']* '
                . ($pullRequest['priority'] > 0 ?
                    ('*_[' . ($pullRequest['priority'] === 2 ? 'Must Have' : 'Nice-to-have') . ']_* ')
                    : ''
                )
                . '<' . $pullRequest['node']['url'] . '|:preston: ' . $pullRequest['node']['repository']['name'] . '#' . $pullRequest['node']['number'] . '>'
                . ' : ' . $pullRequest['node']['title'];
            $slackMessage .= PHP_EOL;

            ++$milestoneCount;
        }

        return $slackMessage;
    }

    protected function checkModuleReadyToRelease(): string
    {
        $modulesNeedRelease = [];
        foreach ($this->moduleFetcher->getModules() as $repository) {
            $checkBranches = $this->moduleChecker->checkBranches('PrestaShop', $repository);
            if ($checkBranches['hasDiffMaster'] && !$checkBranches['hasPRRelease']) {
                $modulesNeedRelease[$repository] = $checkBranches['status'];
            }
        }
        uasort($modulesNeedRelease, function ($a, $b) {
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
                $slackMessage .= ' - <https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> ';
                $slackMessage .= '(' . $status['ahead'] . ' commits / ' . $status['numPRMerged'] . ' PR' . ($status['numPRMerged'] > 1 ? 's' : '') . ')';
                $slackMessage .= PHP_EOL;
            }

            return $slackMessage;
        }

        return '';
    }

    protected function checkModuleImprovements(): string
    {
        $improvements = [];
        foreach ($this->moduleFetcher->getModules() as $repository) {
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
                $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Please remove for the Presthubot analysis';
                continue;
            }

            if ($this->moduleChecker->getRating(ModuleChecker::RATING_BRANCH) != ModuleChecker::RATING_BRANCH_MAX) {
                if (!$report['branch']['isDefault']) {
                    $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Please set the `dev` branch as main branch';
                }
                // Needs release (cf. checkModuleReadyToRelease)
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_DESCRIPTION) != ModuleChecker::RATING_DESCRIPTION_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Please add a description';
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
                        $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Missing file ' . $path;
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
                            $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Invalid file ' . $path;
                        }
                    }
                }
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_ISSUES) != ModuleChecker::RATING_ISSUES_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Please migrate issues to main repository and close issues on the module repository';
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_LABELS) != ModuleChecker::RATING_LABELS_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Please fix labels ';
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_LICENSE) != ModuleChecker::RATING_LICENSE_MAX) {
                $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Invalid license (Check composer.json)';
            }
            if ($this->moduleChecker->getRating(ModuleChecker::RATING_TOPICS) != ModuleChecker::RATING_TOPICS_MAX) {
                $topics = '';
                foreach ($report['githubTopics'] as $key => $value) {
                    if (!$value) {
                        $topics .= (empty($topics) ? $key : ', ' . $key);
                    }
                }
                $improvements[] = '<https://github.com/PrestaShop/' . $repository . '|:preston: ' . $repository . '> Please add missings topics on Github (' . $topics . ')';
            }
        }
        if (!empty($improvements)) {
            $improvements = array_slice($improvements, 0, 10);
            $slackMessage = ':pencil: Modules need some improvements :pencil:' . PHP_EOL;
            foreach ($improvements as $message) {
                $slackMessage .= ' - ' . $message . ' (on master branch)' . PHP_EOL;
            }

            return $slackMessage;
        }

        return '';
    }

    protected function checkStatsQA(): string
    {
        $graphQLQuery = new Query();
        $slackMessage = ':chart_with_upwards_trend: PR Stats for QA :chart_with_upwards_trend:' . PHP_EOL;

        // Number of PR with the label "Waiting for QA", without the label "Waiting for author", filtered by branch
        foreach (self::BRANCH_SUPPORT as $branch) {
            $searchPR = 'repo:PrestaShop/PrestaShop is:pr is:open base:' . $branch . ' ' . Query::LABEL_WAITING_FOR_QA . ' -' . Query::LABEL_WAITING_FOR_AUTHOR . ' -' . Query::LABEL_WAITING_FOR_DEV . ' -' . Query::LABEL_WAITING_FOR_PM . ' -' . Query::LABEL_BLOCKED;
            $graphQLQuery->setQuery($searchPR);
            $count = $this->github->countSearch($graphQLQuery);
            $slackMessage .= '- <https://github.com/search?q=' . urlencode(stripslashes($searchPR)) . '|PR ' . $branch . '> : *' . $count . '*' . PHP_EOL;
        }

        // Number of PR for Modules
        $searchPRModules = 'org:PrestaShop archived:false -repo:PrestaShop/PrestaShop -repo:PrestaShop/prestashop-specs is:pr is:open ' . Query::LABEL_WAITING_FOR_QA . ' -' . Query::LABEL_WAITING_FOR_AUTHOR . ' -' . Query::LABEL_WAITING_FOR_DEV . ' -' . Query::LABEL_WAITING_FOR_PM . ' -' . Query::LABEL_BLOCKED;
        $graphQLQuery->setQuery($searchPRModules);
        $countModules = $this->github->countSearch($graphQLQuery);
        $slackMessage .= '- <https://github.com/search?q=' . urlencode(stripslashes($searchPRModules)) . '|PR Modules> : *' . $countModules . '*' . PHP_EOL;

        // Number of PR for Specs
        $searchPRSpecs = 'repo:PrestaShop/prestashop-specs is:pr is:open ' . Query::LABEL_WAITING_FOR_QA . ' -' . Query::LABEL_WAITING_FOR_AUTHOR . ' -' . Query::LABEL_WAITING_FOR_DEV . ' -' . Query::LABEL_WAITING_FOR_PM . ' -' . Query::LABEL_BLOCKED;
        $graphQLQuery->setQuery($searchPRSpecs);
        $countSpecs = $this->github->countSearch($graphQLQuery);
        $slackMessage .= '- <https://github.com/search?q=' . urlencode(stripslashes($searchPRSpecs)) . '|PR Specs> : *' . $countSpecs . '*' . PHP_EOL;

        // Number of PR with the label "Waiting for QA" AND with the label "Waiting for author"
        $searchPRWaitingForAuthor = 'org:PrestaShop archived:false is:pr is:open ' . Query::LABEL_WAITING_FOR_QA . ' ' . Query::LABEL_WAITING_FOR_AUTHOR . ' -' . Query::LABEL_WAITING_FOR_DEV . ' -' . Query::LABEL_WAITING_FOR_PM;
        $graphQLQuery->setQuery($searchPRWaitingForAuthor);
        $countWaitingForAuthor = $this->github->countSearch($graphQLQuery);
        $slackMessage .= '- <https://github.com/search?q=' . urlencode(stripslashes($searchPRWaitingForAuthor)) . '|PR Waiting for Author> : *' . $countWaitingForAuthor . '*' . PHP_EOL;

        // Number of PR Blocked
        $searchPRWaitingForAuthor = 'org:PrestaShop archived:false is:pr is:open ' . Query::LABEL_WAITING_FOR_QA . ' ' . Query::LABEL_BLOCKED . ' -' . Query::LABEL_WAITING_FOR_DEV . ' -' . Query::LABEL_WAITING_FOR_PM;
        $graphQLQuery->setQuery($searchPRWaitingForAuthor);
        $countWaitingForAuthor = $this->github->countSearch($graphQLQuery);
        $slackMessage .= '- <https://github.com/search?q=' . urlencode(stripslashes($searchPRWaitingForAuthor)) . '|PR Blocked> : *' . $countWaitingForAuthor . '*' . PHP_EOL;

        return $slackMessage;
    }

    protected function needMergeToDevelop(): array
    {
        if (date('D') !== 'Mon') {
            return [];
        }

        $arrayTeamPR = [];
        foreach (Slack::MAINTAINER_MEMBERS as $key => $value) {
            if ($key == $value) {
                continue;
            }
            $arrayTeamPR[$key] = [];
        }
        unset($arrayTeamPR[Slack::MAINTAINER_LEAD]);
        unset($arrayTeamPR['PierreRambaud']);

        $branches = $this->github->getRepoBranches('PrestaShop', 'PrestaShop', false);
        $lastBranch = array_reduce($branches, function ($carry, $item) {
            return version_compare($carry, $item) < 0 ? $item : $carry;
        }, '');

        // Slack Messages
        $arrayMessage = [];
        $slackMessageTitle = ':arrow_right: We are Monday. Don\'t forget to merge `' . $lastBranch . '` in `develop`! :muscle: ' . PHP_EOL;
        foreach ($arrayTeamPR as $maintainer => $arrayPullRequest) {
            $slackMessage = $slackMessageTitle;

            $slackMessage = $this->slack->linkGithubUsername($slackMessage);
            $slackChannel = Slack::MAINTAINER_MEMBERS[$maintainer];
            $slackChannel = str_replace(['<@', '>'], '', $slackChannel);

            $arrayMessage[$slackChannel] = $slackMessage;
        }

        return $arrayMessage;
    }
}
