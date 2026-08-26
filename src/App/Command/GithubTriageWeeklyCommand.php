<?php

namespace Console\App\Command;

use Console\App\Service\Anthropic;
use Console\App\Service\Github;
use Console\App\Service\Github\TriageQuery;
use Console\App\Service\Slack;
use Console\App\Service\Triage\Prompts;
use Console\App\Service\Triage\Renderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Pre-qualifies the past week's issues and pull requests for the sheriff.
 *
 * Ranks what moved so the sheriff reads a sorted list instead of the whole
 * tracker. It proposes and never decides: no label is applied, no board card is
 * moved, nothing is written back to GitHub. The output is a Slack message and a
 * console report.
 *
 * The severity rubric reproduces the project's published classification and the
 * QA team's sorting rules; see src/App/Resources/triage/severity_system.md.
 */
class GithubTriageWeeklyCommand extends Command
{
    private const REPOSITORY = 'PrestaShop/PrestaShop';

    /**
     * Accounts whose activity is machine-generated. Nothing they open needs a
     * human pre-qualification pass.
     */
    private const BOT_AUTHORS = [
        'dependabot',
        'dependabot[bot]',
        'github-actions',
        'github-actions[bot]',
        'ps-jarvis',
        'renovate[bot]',
    ];

    /**
     * A severity label means a maintainer has already ruled on the issue, so
     * there is nothing left to propose and no reason to pay for a verdict.
     *
     * A `Regression` label is handled separately: it settles that one field but
     * says nothing about severity, so those issues are still classified.
     */
    private const ALREADY_RATED = ['Critical', 'Major', 'Minor', 'Trivial'];

    /**
     * Body text past this point rarely changes a severity call, and paying to
     * send a 40 kB stack trace is waste. The cut is visible to the model.
     */
    private const BODY_LIMIT = 6000;

    private const COMMENT_LIMIT = 1200;

    /**
     * @var Github
     */
    protected $github;

    /**
     * @var Anthropic
     */
    protected $anthropic;

    /**
     * @var Slack
     */
    protected $slack;

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * Set when the search hit GitHub's result cap and the week is incomplete.
     *
     * @var bool
     */
    protected $truncated = false;

    /**
     * Items collected but not classified, because the API call failed.
     *
     * @var int
     */
    protected $failures = 0;

    protected function configure(): void
    {
        $this->setName('github:triage:weekly')
            ->setDescription('Pre-qualify the past week\'s issues and PRs and report to Slack')
            ->addOption('ghtoken', null, InputOption::VALUE_OPTIONAL, '', $_ENV['GH_TOKEN'] ?? null)
            ->addOption(
                'anthropic-token',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['ANTHROPIC_API_KEY'] ?? null
            )
            ->addOption('slacktoken', null, InputOption::VALUE_OPTIONAL, '', $_ENV['SLACK_TOKEN'] ?? null)
            ->addOption(
                'slackchannel',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['SLACK_CHANNEL_CORE'] ?? null
            )
            ->addOption('since', null, InputOption::VALUE_OPTIONAL, 'Window start (YYYY-MM-DD)', null)
            ->addOption('until', null, InputOption::VALUE_OPTIONAL, 'Window end (YYYY-MM-DD)', null)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Classify only the first N items', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Render everything but do not post to Slack')
            ->addOption('run-url', null, InputOption::VALUE_OPTIONAL, 'Link the Slack digest back to this run', null)
            ->addOption(
                'report',
                null,
                InputOption::VALUE_OPTIONAL,
                'Where to write the full report',
                __DIR__ . '/../../../var/report/triage-weekly.md'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $this->anthropic = new Anthropic($input->getOption('anthropic-token'));
        $this->slack = new Slack($input->getOption('slacktoken'));
        $this->renderer = new Renderer();

        if (!$this->anthropic->isConfigured()) {
            $output->writeln('<error>ANTHROPIC_API_KEY is not configured</error>');

            return Command::FAILURE;
        }

        $since = $input->getOption('since') ?: date('Y-m-d', strtotime('-7 days'));
        $until = $input->getOption('until') ?: date('Y-m-d');

        $output->writeln(sprintf('Collecting %s from %s to %s...', self::REPOSITORY, $since, $until));
        [$items, $skipped] = $this->collect($since, $until);
        $output->writeln(sprintf(
            '  %d to classify, %d skipped',
            count($items),
            count($skipped)
        ));
        if ($this->truncated) {
            $output->writeln(
                '<comment>Warning: hit GitHub\'s 1000-result search cap — '
                . 'this week is incomplete. Narrow the window.</comment>'
            );
        }

        // Per type rather than across the mixed list: a small run is for eyeballing
        // the rubrics, and both of them need to be exercised.
        $limit = (int) $input->getOption('limit');
        if ($limit > 0) {
            $issues = array_slice(array_values(array_filter(
                $items,
                fn (array $i): bool => $i['type'] === 'issue'
            )), 0, $limit);
            $prs = array_slice(array_values(array_filter(
                $items,
                fn (array $i): bool => $i['type'] === 'pull_request'
            )), 0, $limit);
            $items = array_merge($issues, $prs);
        }

        $collected = count($items);
        $items = $this->classify($items, $output);

        // An empty week and a week where every call failed look the same from
        // here, and must not: a scheduled job that goes green while the agent
        // is broken is how it stays broken.
        if ($items === []) {
            if ($collected > 0) {
                $output->writeln(sprintf(
                    '<error>All %d items failed to classify — nothing was reported.</error>',
                    $collected
                ));

                return Command::FAILURE;
            }

            $output->writeln('<comment>Nothing to report.</comment>');

            return Command::SUCCESS;
        }

        if ($this->failures > 0) {
            $output->writeln(sprintf(
                '<comment>%d of %d items failed to classify and are missing from the report.</comment>',
                $this->failures,
                $collected
            ));
        }

        // The digest is a short thing that drives clicks; the full report is
        // where the items it trimmed can actually be read. Writing it is not
        // optional - without it the digest's "see the full report" leads nowhere.
        $reportPath = (string) $input->getOption('report');
        @mkdir(dirname($reportPath), 0777, true);
        file_put_contents(
            $reportPath,
            $this->renderer->renderMarkdown($items, $since, $until, $skipped)
        );
        $output->writeln('Wrote the full report to ' . $reportPath);

        $message = $this->renderer->renderSlack(
            $items,
            $since,
            $until,
            $input->getOption('run-url')
        );
        $output->writeln('');
        $output->writeln($message);
        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Tokens: %d in (%d from cache), %d out — about $%.2f at list price</info>',
            $this->anthropic->getUsage()['input'],
            $this->anthropic->getUsage()['cacheRead'],
            $this->anthropic->getUsage()['output'],
            $this->anthropic->getEstimatedCost()
        ));

        if ($this->anthropic->getUsage()['cacheRead'] === 0 && count($items) > 1) {
            $output->writeln(
                '<comment>Warning: no cache reads across a multi-item run — '
                . 'something is invalidating the system prompt between calls.</comment>'
            );
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('<comment>Dry run — nothing was posted.</comment>');

            return Command::SUCCESS;
        }

        $channel = $input->getOption('slackchannel');
        if (empty($channel)) {
            $output->writeln('<comment>No Slack channel configured — skipping the post.</comment>');

            return Command::SUCCESS;
        }

        $this->slack->sendNotification($channel, $this->slack->linkGithubUsername($message));
        $output->writeln('<info>Posted to Slack.</info>');

        return Command::SUCCESS;
    }

    /**
     * Gather the window and drop what needs no human pass.
     *
     * Filtering here is what keeps the run cheap: every item dropped is an item
     * nobody pays to classify. Nothing is dropped silently - each exclusion is
     * returned with its reason.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    protected function collect(string $since, string $until): array
    {
        $query = new TriageQuery();
        $query->setQuery(sprintf(
            'repo:%s updated:%s..%s sort:updated-desc',
            self::REPOSITORY,
            $since,
            $until
        ));

        $items = [];
        $skipped = [];

        $edges = $this->github->search($query);
        // GitHub search returns at most 1000 results. Hitting that means the
        // window silently lost items, which is exactly the kind of quiet
        // truncation the rest of this command works to avoid.
        if (count($edges) >= 1000) {
            $this->truncated = true;
        }

        foreach ($edges as $edge) {
            $node = $edge['node'];
            if (empty($node)) {
                continue;
            }

            $isIssue = ($node['__typename'] ?? '') === 'Issue';
            $labels = array_column($node['labels']['nodes'] ?? [], 'name');
            $author = $node['author']['login'] ?? 'ghost';
            $number = $node['number'];

            // A closed item has been dealt with; the sheriff pre-qualifies what
            // is still open.
            if (($node['state'] ?? 'OPEN') !== 'OPEN') {
                $skipped[] = ['number' => $number, 'reason' => 'not open'];

                continue;
            }
            if (in_array($author, self::BOT_AUTHORS, true)) {
                $skipped[] = ['number' => $number, 'reason' => 'bot author (' . $author . ')'];

                continue;
            }
            if ($isIssue && array_intersect(self::ALREADY_RATED, $labels) !== []) {
                $skipped[] = ['number' => $number, 'reason' => 'already rated by a maintainer'];

                continue;
            }

            $items[] = [
                'number' => $number,
                'type' => $isIssue ? 'issue' : 'pull_request',
                'title' => $node['title'],
                'url' => $node['url'],
                'body' => $this->truncate($node['body'] ?? '', self::BODY_LIMIT),
                'author' => $author,
                'authorAssociation' => $node['authorAssociation'] ?? 'NONE',
                'labels' => $labels,
                'milestone' => $node['milestone']['title'] ?? null,
                'createdAt' => $node['createdAt'],
                'updatedAt' => $node['updatedAt'],
                'daysSinceUpdate' => (int) floor(
                    (time() - strtotime($node['updatedAt'])) / 86400
                ),
                'commentCount' => $node['comments']['totalCount'] ?? 0,
                'comments' => $this->simplifyComments($node['comments']['nodes'] ?? []),
                'reactions' => $node['reactions']['totalCount'] ?? 0,
                'isDraft' => $node['isDraft'] ?? false,
                'baseBranch' => $node['baseRefName'] ?? null,
                'reviewDecision' => $node['reviewDecision'] ?? null,
                'reviews' => $node['reviews']['nodes'] ?? [],
                'changedFiles' => $node['changedFiles'] ?? null,
                'additions' => $node['additions'] ?? null,
                'deletions' => $node['deletions'] ?? null,
                'alreadyMarkedRegression' => in_array('Regression', $labels, true),
                'duplicateCandidates' => [],
            ];
        }

        // One extra search per issue. Grounding the model in a real shortlist is
        // what stops it inventing issue numbers: it may only pick from what it
        // is handed, or return nothing.
        foreach ($items as $index => $item) {
            if ($item['type'] === 'issue') {
                $items[$index]['duplicateCandidates'] = $this->findDuplicates($item);
            }
        }

        return [$items, $skipped];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    protected function classify(array $items, OutputInterface $output): array
    {
        $systems = [
            'issue' => Anthropic::cachedSystem(Prompts::severity()),
            'pull_request' => Anthropic::cachedSystem(Prompts::pullRequest()),
        ];
        $schemaFor = [
            'issue' => Prompts::schema('issue'),
            'pull_request' => Prompts::schema('pull_request'),
        ];

        $classified = [];
        $total = count($items);

        foreach ($items as $index => $item) {
            $output->writeln(sprintf(
                '  [%d/%d] %s #%d',
                $index + 1,
                $total,
                $item['type'],
                $item['number']
            ));

            try {
                $verdict = $this->anthropic->classify(
                    $systems[$item['type']],
                    $schemaFor[$item['type']],
                    $this->render($item)
                );
            } catch (\RuntimeException $e) {
                $output->writeln('    <error>' . $e->getMessage() . '</error>');
                ++$this->failures;

                continue;
            }

            // A maintainer already applying the Regression label settles it;
            // the proposal does not get to contradict a human.
            if ($item['alreadyMarkedRegression']) {
                $verdict['looks_like_regression'] = true;
            }

            $item['verdict'] = $verdict;
            $classified[] = $item;
        }

        return $classified;
    }

    /**
     * Open issues whose titles share keywords with this one.
     *
     * @param array<string, mixed> $issue
     *
     * @return array<int, array{number: int, title: string}>
     */
    protected function findDuplicates(array $issue): array
    {
        $keywords = $this->titleKeywords($issue['title']);
        if (count($keywords) < 2) {
            return [];
        }

        // REST rather than the GraphQL helper on purpose. That helper pages
        // through every result before returning, and TriageQuery would drag a
        // full body, comments and reviews along for each one - all of it
        // discarded here, where only a number and a title are needed.
        $candidates = [];
        try {
            $results = $this->github->getClient()->api('search')->issues(sprintf(
                'repo:%s is:issue is:open in:title %s',
                self::REPOSITORY,
                implode(' ', $keywords)
            ));

            foreach ($results['items'] ?? [] as $found) {
                if (($found['number'] ?? null) === null || $found['number'] === $issue['number']) {
                    continue;
                }
                $candidates[] = ['number' => $found['number'], 'title' => $found['title'] ?? ''];
                if (count($candidates) >= 10) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            // A duplicate shortlist is a nicety; losing it must not cost the run.
            return [];
        }

        return $candidates;
    }

    /**
     * Words from a title worth searching on.
     *
     * Without the stopword filter a search for "the" and "when" returns the
     * whole tracker and the shortlist is worthless.
     *
     * @return array<int, string>
     */
    protected function titleKeywords(string $title, int $limit = 5): array
    {
        $stopwords = [
            'the', 'and', 'for', 'with', 'when', 'from', 'that', 'this', 'have',
            'has', 'not', 'are', 'but', 'you', 'your', 'its', 'can', 'cant',
            'does', 'doesnt', 'after', 'before', 'into', 'there', 'then', 'than',
            'was', 'were', 'issue', 'bug', 'problem', 'error', 'prestashop', 'shop',
        ];

        preg_match_all('/[A-Za-z][A-Za-z0-9_-]{2,}/', mb_strtolower($title), $matches);

        $keywords = [];
        foreach ($matches[0] as $word) {
            if (in_array($word, $stopwords, true) || in_array($word, $keywords, true)) {
                continue;
            }
            $keywords[] = $word;
            if (count($keywords) === $limit) {
                break;
            }
        }

        return $keywords;
    }

    /**
     * Lay out one item for the model, in a stable field order.
     *
     * @param array<string, mixed> $item
     */
    protected function render(array $item): string
    {
        $isIssue = $item['type'] === 'issue';
        $lines = [
            sprintf('# %s #%d: %s', $isIssue ? 'Issue' : 'Pull request', $item['number'], $item['title']),
            '',
            '- URL: ' . $item['url'],
            sprintf('- Opened by: %s (%s)', $item['author'], $item['authorAssociation']),
            '- Created: ' . $item['createdAt'],
            sprintf('- Last updated: %s (%d days ago)', $item['updatedAt'], $item['daysSinceUpdate']),
            '- Existing labels: ' . (implode(', ', $item['labels']) ?: 'none'),
            '- Milestone: ' . ($item['milestone'] ?? 'none'),
            '- Comments: ' . $item['commentCount'],
        ];

        if ($isIssue) {
            $lines[] = '- Reactions: ' . $item['reactions'];
        } else {
            $lines[] = '- Base branch: ' . ($item['baseBranch'] ?? 'unknown');
            $lines[] = '- Draft: ' . ($item['isDraft'] ? 'yes' : 'no');
            $lines[] = '- Review decision: ' . ($item['reviewDecision'] ?? 'none yet');
            $lines[] = sprintf(
                '- Size: %s files, +%s/-%s',
                $item['changedFiles'] ?? '?',
                $item['additions'] ?? '?',
                $item['deletions'] ?? '?'
            );
            $lines[] = '';
            $lines[] = '## Reviews (most recent last)';
            $lines[] = '';
            if ($item['reviews'] === []) {
                $lines[] = 'None.';
            }
            foreach ($item['reviews'] as $review) {
                $lines[] = sprintf(
                    '- %s: %s on %s',
                    $review['author']['login'] ?? 'unknown',
                    $review['state'] ?? '?',
                    $review['submittedAt'] ?? '?'
                );
            }
        }

        $lines[] = '';
        $lines[] = $isIssue ? '## Body' : '## Description';
        $lines[] = '';
        $lines[] = $item['body'] !== '' ? $item['body'] : '_(empty)_';

        if ($item['comments'] !== []) {
            $lines[] = '';
            $lines[] = '## Most recent comments';
            foreach ($item['comments'] as $comment) {
                $lines[] = '';
                $lines[] = sprintf(
                    '### %s (%s) on %s',
                    $comment['author'],
                    $comment['association'],
                    $comment['createdAt']
                );
                $lines[] = '';
                $lines[] = $comment['body'];
            }
        }

        if ($isIssue) {
            $lines[] = '';
            $lines[] = '## Candidate duplicates';
            $lines[] = '';
            $candidates = $item['duplicateCandidates'] ?? [];
            if ($candidates === []) {
                $lines[] = 'None found - return an empty list.';
            } else {
                $lines[] = 'You may only return numbers from this list, and only if the '
                    . 'other issue describes the same underlying defect:';
                foreach ($candidates as $candidate) {
                    $lines[] = sprintf('- #%d: %s', $candidate['number'], $candidate['title']);
                }
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     *
     * @return array<int, array<string, string>>
     */
    protected function simplifyComments(array $nodes): array
    {
        $comments = [];
        foreach ($nodes as $node) {
            $comments[] = [
                'author' => $node['author']['login'] ?? 'ghost',
                'association' => $node['authorAssociation'] ?? 'NONE',
                'createdAt' => $node['createdAt'] ?? '',
                'body' => $this->truncate($node['body'] ?? '', self::COMMENT_LIMIT),
            ];
        }

        return $comments;
    }

    protected function truncate(?string $text, int $limit): string
    {
        $text = trim((string) $text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit)
            . sprintf('%s%s[... truncated, %d more characters]', PHP_EOL, PHP_EOL, mb_strlen($text) - $limit);
    }
}
