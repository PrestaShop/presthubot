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
 
class SlackNotifierQACommand extends Command
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
        $this->setName('slack:notifier:qa')
            ->setDescription('Notify QA Team on Slack every day')
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
                $_ENV['SLACK_CHANNEL_QA'] ?? null
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
}
