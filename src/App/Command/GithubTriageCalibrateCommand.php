<?php

namespace Console\App\Command;

use Console\App\Service\Anthropic;
use Console\App\Service\Github;
use Console\App\Service\Github\Query;
use Console\App\Service\Triage\Prompts;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Measures how far the severity rubric agrees with the maintainers.
 *
 * Without this number the weekly agent produces confident verdicts nobody can
 * check. This is also the concrete answer to "train it on five years of closed
 * issues": there is no fine-tuning, the corpus is used for grounding and for
 * measurement.
 *
 * Closed issues carrying exactly one severity label are split once, and
 * deterministically, into two halves:
 *
 *   - a pool, which the worked examples in severity_examples.md are mined from
 *   - a held-out set, which is scored and never appears in a prompt
 *
 * Mining examples from the held-out half would hand over the answers and make
 * the score meaningless, which is why the split seed is fixed and shared.
 *
 * Not scheduled. Run it when the rubric changes: a fixed seed means two runs
 * are comparable, so it doubles as a regression test on the prompt.
 */
class GithubTriageCalibrateCommand extends Command
{
    /**
     * The only repository with a severity-labelled history to measure against.
     * No native module carries those labels, so calibration has no ground truth
     * anywhere else - the option exists for completeness, not because pointing
     * it elsewhere would produce a meaningful number.
     */
    private const DEFAULT_REPOSITORY = 'PrestaShop/PrestaShop';

    /**
     * @var string
     */
    protected $repository = self::DEFAULT_REPOSITORY;

    private const RESOURCES = __DIR__ . '/../Resources/triage';

    private const SEVERITIES = ['Critical', 'Major', 'Minor', 'Trivial'];

    /**
     * Stratified rather than random. A random sample of this corpus would hold
     * roughly ten Criticals, far too few to say anything about the one class
     * that decides whether the top of the weekly list can be trusted.
     */
    private const EVAL_PER_CLASS = 100;

    private const FEWSHOT_PER_CLASS = 6;

    /**
     * Fixed so that re-running after a prompt change compares like with like
     * instead of reshuffling the ground underneath it.
     */
    private const SPLIT_SEED = 42;

    /**
     * GitHub search returns at most 1000 results per query and `Minor` alone
     * exceeds that over five years, so the corpus is fetched year by year.
     * Without the shard it would come back silently truncated and the class
     * balance would be wrong.
     */
    private const FIRST_YEAR = 2021;

    /**
     * @var Github
     */
    protected $github;

    /**
     * @var Anthropic
     */
    protected $anthropic;

    protected function configure(): void
    {
        $this->setName('github:triage:calibrate')
            ->setDescription('Score the triage severity rubric against maintainer labels')
            ->addOption('ghtoken', null, InputOption::VALUE_OPTIONAL, '', $_ENV['GH_TOKEN'] ?? null)
            ->addOption(
                'repository',
                null,
                InputOption::VALUE_OPTIONAL,
                'Repository whose labelled history to score against',
                self::DEFAULT_REPOSITORY
            )
            ->addOption(
                'anthropic-token',
                null,
                InputOption::VALUE_OPTIONAL,
                '',
                $_ENV['ANTHROPIC_API_KEY'] ?? null
            )
            ->addOption('mine', null, InputOption::VALUE_NONE, 'Regenerate severity_examples.md and stop')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Refetch the corpus instead of using the cache')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Score only the first N held-out issues', 0)
            ->addOption(
                'report',
                null,
                InputOption::VALUE_OPTIONAL,
                'Where to write the scored report',
                __DIR__ . '/../../../var/report/triage-calibration.md'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->github = new Github($input->getOption('ghtoken'));
        $this->repository = (string) $input->getOption('repository');

        $corpus = $this->corpus((bool) $input->getOption('refresh'), $output);
        [$pool, $heldOut] = $this->split($corpus);

        $output->writeln(sprintf(
            'Corpus: %d issues — %d in the example pool, %d held out',
            count($corpus),
            count($pool),
            count($heldOut)
        ));

        if ($input->getOption('mine')) {
            $path = $this->mine($pool);
            $output->writeln('<info>Wrote ' . $path . '</info>');

            return Command::SUCCESS;
        }

        $this->anthropic = new Anthropic($input->getOption('anthropic-token'));
        if (!$this->anthropic->isConfigured()) {
            $output->writeln('<error>ANTHROPIC_API_KEY is not configured</error>');

            return Command::FAILURE;
        }

        $limit = (int) $input->getOption('limit');
        if ($limit > 0) {
            $heldOut = array_slice($heldOut, 0, $limit);
        }

        [$predictions, $failures] = $this->evaluate($heldOut, $output);
        $report = $this->score($heldOut, $predictions, $failures);

        $path = (string) $input->getOption('report');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $report);

        $output->writeln('');
        $output->write($report);
        $output->writeln('<info>Wrote ' . $path . '</info>');

        return Command::SUCCESS;
    }

    /**
     * Closed issues carrying exactly one severity label.
     *
     * @return array<int, array{number: int, title: string, body: string, truth: string}>
     */
    protected function corpus(bool $refresh, OutputInterface $output): array
    {
        $cache = __DIR__ . '/../../../var/cache/triage-corpus.json';

        if (!$refresh && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached) && $cached !== []) {
                $output->writeln('Using the cached corpus (--refresh to refetch)');

                return $cached;
            }
        }

        $corpus = [];
        $lastYear = (int) date('Y');

        foreach (self::SEVERITIES as $severity) {
            $exclusions = '';
            foreach (self::SEVERITIES as $other) {
                if ($other !== $severity) {
                    $exclusions .= ' -label:' . $other;
                }
            }

            $found = 0;
            for ($year = self::FIRST_YEAR; $year <= $lastYear; ++$year) {
                $query = new Query();
                $query->setQuery(sprintf(
                    'repo:%s is:issue is:closed label:%s%s created:%d-01-01..%d-12-31',
                    $this->repository,
                    $severity,
                    $exclusions,
                    $year,
                    $year
                ));

                foreach ($this->github->search($query) as $edge) {
                    $node = $edge['node'] ?? null;
                    if (empty($node) || !isset($node['number'])) {
                        continue;
                    }
                    $corpus[] = [
                        'number' => $node['number'],
                        'title' => $node['title'] ?? '',
                        'body' => (string) ($node['body'] ?? ''),
                        'truth' => $severity,
                    ];
                    ++$found;
                }
            }

            $output->writeln(sprintf('  %d %s', $found, $severity));
        }

        @mkdir(dirname($cache), 0777, true);
        file_put_contents($cache, json_encode($corpus));

        return $corpus;
    }

    /**
     * Stratified, deterministic split into an example pool and a held-out set.
     *
     * @param array<int, array<string, mixed>> $corpus
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    protected function split(array $corpus): array
    {
        $byClass = array_fill_keys(self::SEVERITIES, []);
        foreach ($corpus as $issue) {
            $byClass[$issue['truth']][] = $issue;
        }

        $pool = [];
        $heldOut = [];

        foreach (self::SEVERITIES as $severity) {
            $items = $byClass[$severity];
            // Sort before shuffling so the split does not depend on the order
            // GitHub happened to return.
            usort($items, fn (array $a, array $b): int => $a['number'] <=> $b['number']);
            mt_srand(self::SPLIT_SEED);
            shuffle($items);

            $take = min(self::EVAL_PER_CLASS, intdiv(count($items), 2));
            $byClass[$severity] = array_slice($items, 0, $take);
            $pool = array_merge($pool, array_slice($items, $take));
        }

        // Interleaved round-robin rather than class after class, so that any
        // prefix of the set is still stratified. Appending one class at a time
        // would make --limit=20 score nothing but Criticals and produce a
        // confusion matrix with a single populated row.
        for ($index = 0;; ++$index) {
            $added = false;
            foreach (self::SEVERITIES as $severity) {
                if (isset($byClass[$severity][$index])) {
                    $heldOut[] = $byClass[$severity][$index];
                    $added = true;
                }
            }
            if (!$added) {
                break;
            }
        }

        return [$pool, $heldOut];
    }

    /**
     * Write the worked examples, drawn from the pool half only.
     *
     * @param array<int, array<string, mixed>> $pool
     */
    protected function mine(array $pool): string
    {
        $lines = [
            '# Worked examples',
            '',
            'Real issues from this repository, with the severity the maintainers',
            'actually applied. Use them to calibrate the boundaries - especially',
            'Critical vs Major, where the workaround question decides it.',
            '',
        ];

        foreach (self::SEVERITIES as $severity) {
            $candidates = [];
            foreach ($pool as $issue) {
                if ($issue['truth'] !== $severity) {
                    continue;
                }
                $body = $this->stripBoilerplate($issue['body']);
                if (mb_strlen($body) > 200) {
                    $candidates[] = ['title' => $issue['title'], 'body' => $body];
                }
            }
            mt_srand(self::SPLIT_SEED);
            shuffle($candidates);

            foreach (array_slice($candidates, 0, self::FEWSHOT_PER_CLASS) as $example) {
                $lines[] = sprintf('## %s — %s', $severity, $example['title']);
                $lines[] = '';
                $lines[] = mb_substr($example['body'], 0, 600);
                $lines[] = '';
            }
        }

        $path = self::RESOURCES . '/severity_examples.md';
        file_put_contents($path, implode(PHP_EOL, $lines));

        return $path;
    }

    /**
     * Drop the parts of an issue body that are identical on every report.
     *
     * The template preamble carries no signal and, left in, would be roughly a
     * third of every mined example.
     */
    protected function stripBoilerplate(string $body): string
    {
        $body = (string) preg_replace('/<!--.*?-->/s', ' ', $body);
        $body = (string) preg_replace(
            '/###?\s*Prerequisites.*?(?=###?\s*(?:Describe|Expected|Steps|Additional)|$)/is',
            ' ',
            $body
        );
        $body = (string) preg_replace('/-\s*\[[xX ]\]\s*[^\n]*/m', ' ', $body);
        $body = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '[screenshot]', $body);

        return trim((string) preg_replace('/\s+/', ' ', $body));
    }

    /**
     * Run the held-out set through the rubric.
     *
     * Sequential rather than batched on purpose. The Batch API halves the cost
     * but targets completion within 24 hours, while a GitHub-hosted job is cut
     * off at six; a few dollars is a poor trade for a run that can vanish.
     *
     * @param array<int, array<string, mixed>> $heldOut
     *
     * @return array{0: array<int, string>, 1: int}
     */
    protected function evaluate(array $heldOut, OutputInterface $output): array
    {
        // Through Prompts, so the calibration measures byte-for-byte what the
        // weekly run executes. Assembling it here as well would let the two
        // drift without anything failing.
        $system = Anthropic::cachedSystem(Prompts::severity());
        $schema = Prompts::schema('issue');

        $predictions = [];
        $failures = 0;
        $total = count($heldOut);

        foreach ($heldOut as $index => $issue) {
            if ($index % 25 === 0) {
                $output->writeln(sprintf('  %d/%d scored', $index, $total));
            }

            try {
                $verdict = $this->anthropic->classify(
                    $system,
                    $schema,
                    $this->renderForEval($issue)
                );
            } catch (\RuntimeException $e) {
                ++$failures;

                continue;
            }

            if (isset($verdict['severity'])) {
                $predictions[$issue['number']] = $verdict['severity'];
            } else {
                ++$failures;
            }
        }

        return [$predictions, $failures];
    }

    /**
     * The issue as the rubric sees it during evaluation.
     *
     * Deliberately bare: no labels, no milestone, no comments. Anything the
     * maintainers added after triage would leak the answer.
     *
     * @param array<string, mixed> $issue
     */
    protected function renderForEval(array $issue): string
    {
        return implode(PHP_EOL, [
            sprintf('# Issue #%d: %s', $issue['number'], $issue['title']),
            '',
            '- Existing labels: none',
            '- Milestone: none',
            '- Comments: 0',
            '',
            '## Body',
            '',
            $issue['body'] !== '' ? mb_substr($issue['body'], 0, 6000) : '_(empty)_',
            '',
            '## Candidate duplicates',
            '',
            'None supplied - return an empty list.',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $heldOut
     * @param array<int, string> $predictions
     */
    protected function score(array $heldOut, array $predictions, int $failures): string
    {
        $rank = array_flip(self::SEVERITIES);
        $matrix = [];
        foreach (self::SEVERITIES as $truth) {
            $matrix[$truth] = array_fill_keys(self::SEVERITIES, 0);
        }

        $scored = 0;
        $exact = 0;
        $offByOne = 0;

        foreach ($heldOut as $issue) {
            $predicted = $predictions[$issue['number']] ?? null;
            if ($predicted === null || !isset($matrix[$issue['truth']][$predicted])) {
                continue;
            }
            ++$matrix[$issue['truth']][$predicted];
            ++$scored;
            $distance = abs($rank[$issue['truth']] - $rank[$predicted]);
            if ($distance === 0) {
                ++$exact;
            } elseif ($distance === 1) {
                ++$offByOne;
            }
        }

        if ($scored === 0) {
            return '# Calibration' . PHP_EOL . PHP_EOL . 'No issues scored.' . PHP_EOL;
        }

        $lines = [
            '# Calibration against maintainer labels',
            '',
            sprintf(
                'Held-out set: **%d** closed issues, each carrying exactly one severity '
                . 'label applied by a maintainer. The worked examples were mined from a '
                . 'disjoint pool, so none of these were shown to the model.',
                $scored
            ),
            '',
            sprintf('- Exact agreement: **%d/%d** (%.1f%%)', $exact, $scored, $exact / $scored * 100),
            sprintf('- Off by one level: %d/%d (%.1f%%)', $offByOne, $scored, $offByOne / $scored * 100),
            sprintf(
                '- Off by two or more: %d/%d (%.1f%%)',
                $scored - $exact - $offByOne,
                $scored,
                ($scored - $exact - $offByOne) / $scored * 100
            ),
        ];
        if ($failures > 0) {
            $lines[] = sprintf('- Failed to classify: %d', $failures);
        }
        $lines[] = sprintf('- Estimated cost of this run: $%.2f', $this->anthropic->getEstimatedCost());
        $lines[] = '';

        $lines[] = '## Confusion matrix';
        $lines[] = '';
        $lines[] = 'Rows are what the maintainers labelled, columns what the rubric proposed.';
        $lines[] = '';
        $lines[] = '| maintainer \\ rubric | ' . implode(' | ', self::SEVERITIES) . ' | recall |';
        $lines[] = '|---|' . str_repeat('---|', count(self::SEVERITIES) + 1);

        $criticalRecall = 0.0;
        foreach (self::SEVERITIES as $truth) {
            $rowTotal = array_sum($matrix[$truth]);
            $cells = [];
            foreach (self::SEVERITIES as $predicted) {
                $cells[] = (string) $matrix[$truth][$predicted];
            }
            $recall = $rowTotal > 0 ? $matrix[$truth][$truth] / $rowTotal : 0.0;
            if ($truth === 'Critical') {
                $criticalRecall = $recall;
            }
            $lines[] = sprintf('| **%s** | %s | %.0f%% |', $truth, implode(' | ', $cells), $recall * 100);
        }

        $lines[] = '';
        $lines[] = '## Per-class precision';
        $lines[] = '';
        $lines[] = '| level | precision | proposed n |';
        $lines[] = '|---|---|---|';
        foreach (self::SEVERITIES as $level) {
            $columnTotal = 0;
            foreach (self::SEVERITIES as $truth) {
                $columnTotal += $matrix[$truth][$level];
            }
            $precision = $columnTotal > 0 ? $matrix[$level][$level] / $columnTotal : 0.0;
            $lines[] = sprintf('| %s | %.0f%% | %d |', $level, $precision * 100, $columnTotal);
        }

        $lines[] = '';
        $lines[] = '## Reading this';
        $lines[] = '';
        $lines[] = sprintf(
            '**Critical recall is %.0f%%** — of the issues maintainers called Critical, '
            . 'that share was also proposed Critical. This is the number that decides '
            . 'whether the top of the weekly list can be trusted; a miss here is an issue '
            . 'the sheriff never sees ranked.',
            $criticalRecall * 100
        );
        $lines[] = '';
        $lines[] = 'Exact agreement understates usefulness and off-by-two overstates harm: '
            . 'a Major proposed as Critical costs one glance, a Critical proposed as Minor '
            . 'is the failure that matters. Weigh the matrix, not the headline percentage.';
        $lines[] = '';
        $lines[] = 'The corpus is heavily imbalanced, so plain accuracy would look good while '
            . 'saying nothing. And these labels are five years of decisions by many different '
            . 'people, so this measures agreement with past practice, not correctness.';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
