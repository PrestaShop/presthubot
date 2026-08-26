<?php

namespace Console\App\Service\Triage;

/**
 * Formats classified items for the two audiences: a Slack message the sheriff
 * reads on Monday, and a fuller console report.
 *
 * Slack's chat.postMessage is called with a plain mrkdwn `text` by
 * Console\App\Service\Slack, so everything here is mrkdwn rather than Block Kit.
 */
class Renderer
{
    public const SEVERITIES = ['Critical', 'Major', 'Minor', 'Trivial'];

    private const ATTENTION_ICON = [
        'blocking' => ':red_circle:',
        'soon' => ':large_orange_circle:',
        'routine' => ':white_circle:',
    ];

    private const SEVERITY_ICON = [
        'Critical' => ':red_circle:',
        'Major' => ':large_orange_circle:',
        'Minor' => ':large_yellow_circle:',
        'Trivial' => ':white_circle:',
    ];

    /**
     * Items listed in the attention block before the rest is rolled up. The
     * overflow is always announced - a silently truncated list reads as "that
     * was everything".
     */
    private const PER_BAND = 5;

    /**
     * The short list the sheriff reads if they read nothing else.
     *
     * Deliberately narrow. Blocking PRs have their own band and a misrouted
     * branch is hygiene, not urgency; including either buries the handful of
     * items that genuinely need a human this week.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array{item: array<string, mixed>, reasons: array<int, string>}>
     */
    public function needsAttention(array $items): array
    {
        $flagged = [];

        foreach ($items as $item) {
            $verdict = $item['verdict'];
            $reasons = [];

            if ($item['type'] === 'issue') {
                $isBug = ($verdict['kind'] ?? 'bug_report') === 'bug_report';
                if (!empty($verdict['security_suspicion'])) {
                    $reasons[] = 'possible security report';
                }
                if (!empty($verdict['needs_human_now'])) {
                    $reasons[] = 'flagged as needing a look this week';
                }
                if ($isBug && ($verdict['severity'] ?? '') === 'Critical') {
                    $reasons[] = 'proposed Critical';
                }
                // A regression outranks an older bug of the same severity when
                // priority is set, so it earns the week even at Major.
                if ($isBug
                    && !empty($verdict['looks_like_regression'])
                    && in_array($verdict['severity'] ?? '', ['Critical', 'Major'], true)
                ) {
                    $reasons[] = 'looks like a regression';
                }
            } elseif (!empty($verdict['is_community_pr_unanswered'])) {
                $reasons[] = 'community PR with no maintainer response';
            }

            if ($reasons !== []) {
                $flagged[] = ['item' => $item, 'reasons' => $reasons];
            }
        }

        return $flagged;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    public function bugReports(array $items): array
    {
        return array_values(array_filter($items, function (array $item): bool {
            return $item['type'] === 'issue'
                && ($item['verdict']['kind'] ?? 'bug_report') === 'bug_report';
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    /**
     * The Monday digest.
     *
     * Deliberately not a copy of the report. Slack collapses anything much past
     * four thousand characters behind a "See more", so listing every Minor
     * would hide the part that matters. What survives here is what the sheriff
     * has to act on - the attention list and any Critical - plus a census of
     * the rest and a link to the full report.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function renderSlack(
        array $items,
        string $since,
        string $until,
        ?string $runUrl = null,
    ): string {
        $bugs = $this->bugReports($items);
        $prs = array_values(array_filter($items, fn (array $i): bool => $i['type'] === 'pull_request'));

        $lines = [
            sprintf('*Weekly pre-qualification* — %s to %s', $since, $until),
            sprintf(
                '_%d bug reports, %d open pull requests. Proposals only — nothing was labelled._',
                count($bugs),
                count($prs)
            ),
        ];

        $flagged = $this->needsAttention($items);
        if ($flagged !== []) {
            $lines[] = '';
            $lines[] = sprintf('*Needs you this week (%d)*', count($flagged));
            foreach (array_slice($flagged, 0, self::PER_BAND) as $entry) {
                $lines[] = sprintf(
                    ':red_circle: <%s|#%d> %s — _%s_',
                    $entry['item']['url'],
                    $entry['item']['number'],
                    $this->trim($entry['item']['title'], 70),
                    implode(', ', $entry['reasons'])
                );
            }
            if (count($flagged) > self::PER_BAND) {
                $lines[] = sprintf('_+ %d more in the full report_', count($flagged) - self::PER_BAND);
            }
        }

        // Critical is the one band worth listing in full: there are rarely more
        // than a couple, and each one is a decision the sheriff has to make now.
        $critical = $this->bySeverity($bugs, 'Critical');
        if ($critical !== []) {
            $lines[] = '';
            $lines[] = sprintf('*Proposed Critical (%d)*', count($critical));
            foreach ($critical as $item) {
                $lines[] = sprintf(
                    '%s <%s|#%d> %s — _%s_',
                    self::SEVERITY_ICON['Critical'],
                    $item['url'],
                    $item['number'],
                    $this->trim($item['title'], 70),
                    $this->trim($item['verdict']['rationale'], 120)
                );
            }
        }

        $blocking = array_values(array_filter(
            $prs,
            fn (array $i): bool => ($i['verdict']['attention'] ?? '') === 'blocking'
        ));
        if ($blocking !== []) {
            $lines[] = '';
            $lines[] = sprintf('*Pull requests · blocking (%d)*', count($blocking));
            foreach (array_slice($blocking, 0, self::PER_BAND) as $item) {
                $lines[] = sprintf(
                    '%s <%s|#%d> %s — waiting on *%s*, %dd idle',
                    self::ATTENTION_ICON['blocking'],
                    $item['url'],
                    $item['number'],
                    $this->trim($item['title'], 70),
                    $item['verdict']['waiting_on'] ?? 'unknown',
                    $item['daysSinceUpdate']
                );
            }
            if (count($blocking) > self::PER_BAND) {
                $lines[] = sprintf(
                    '_+ %d more in the full report_',
                    count($blocking) - self::PER_BAND
                );
            }
        }

        $census = [];
        foreach (['Major', 'Minor', 'Trivial'] as $severity) {
            $count = count($this->bySeverity($bugs, $severity));
            if ($count > 0) {
                $census[] = sprintf('%s %d', $severity, $count);
            }
        }
        $prCensus = [];
        foreach (['soon', 'routine'] as $level) {
            $count = count(array_filter(
                $prs,
                fn (array $i): bool => ($i['verdict']['attention'] ?? '') === $level
            ));
            if ($count > 0) {
                $prCensus[] = sprintf('%d %s', $count, $level);
            }
        }

        if ($census !== [] || $prCensus !== []) {
            $lines[] = '';
            $parts = [];
            if ($census !== []) {
                $parts[] = 'Issues — ' . implode(' · ', $census);
            }
            if ($prCensus !== []) {
                $parts[] = 'PRs — ' . implode(' · ', $prCensus);
            }
            $lines[] = '*The rest:* ' . implode('  |  ', $parts);
        }

        if ($runUrl !== null) {
            $lines[] = '';
            $lines[] = sprintf('_<%s|Full report, with every item and the reasoning.>_', $runUrl);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * The category and component the rubric proposed, as one cell.
     *
     * @param array<string, mixed> $verdict
     */
    private function area(array $verdict): string
    {
        $category = $verdict['category'] ?? '-';
        $component = $verdict['component'] ?? 'none';

        return $component === 'none'
            ? (string) $category
            : sprintf('%s / %s', $category, $component);
    }

    /**
     * @param array<int, array<string, mixed>> $bugs
     *
     * @return array<int, array<string, mixed>>
     */
    private function bySeverity(array $bugs, string $severity): array
    {
        return array_values(array_filter(
            $bugs,
            fn (array $i): bool => ($i['verdict']['severity'] ?? '') === $severity
        ));
    }

    /**
     * The full report - every item, no caps. This is what the Slack digest
     * links to, and the only place the trimmed overflow can be read.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array{number: int, reason: string}> $skipped
     */
    public function renderMarkdown(
        array $items,
        string $since,
        string $until,
        array $skipped = [],
    ): string {
        $bugs = $this->bugReports($items);
        $prs = array_values(array_filter($items, fn (array $i): bool => $i['type'] === 'pull_request'));
        $others = array_values(array_filter(
            $items,
            fn (array $i): bool => $i['type'] === 'issue'
                && ($i['verdict']['kind'] ?? 'bug_report') !== 'bug_report'
        ));

        $out = [
            sprintf('# Weekly pre-qualification — %s to %s', $since, $until),
            '',
            sprintf(
                '%d bug reports, %d other issues and %d open pull requests touched this week.',
                count($bugs),
                count($others),
                count($prs)
            ),
            '',
            '> **These are proposals.** Nothing was labelled and no board card was moved. '
                . 'Every line below is a suggestion for the sheriff to accept, correct, or ignore.',
            '',
        ];

        $flagged = $this->needsAttention($items);
        $out[] = sprintf('## Needs you this week (%d)', count($flagged));
        $out[] = '';
        if ($flagged === []) {
            $out[] = '_Nothing flagged._';
        }
        foreach ($flagged as $entry) {
            $out[] = sprintf(
                '- [#%d](%s) %s — **%s**',
                $entry['item']['number'],
                $entry['item']['url'],
                $entry['item']['title'],
                implode(', ', $entry['reasons'])
            );
        }
        $out[] = '';

        $out[] = '## Issues by proposed severity';
        $out[] = '';
        foreach (self::SEVERITIES as $severity) {
            $band = array_values(array_filter(
                $bugs,
                fn (array $i): bool => ($i['verdict']['severity'] ?? '') === $severity
            ));
            $out[] = sprintf('### %s (%d)', $severity, count($band));
            $out[] = '';
            if ($band === []) {
                $out[] = '_None._';
                $out[] = '';

                continue;
            }
            $out[] = '| Issue | Area | Confidence | Next step | Why |';
            $out[] = '|---|---|---|---|---|';
            foreach ($band as $item) {
                $marks = [];
                if (!empty($item['verdict']['looks_like_regression'])) {
                    $marks[] = '`regression`';
                }
                if (!empty($item['verdict']['security_suspicion'])) {
                    $marks[] = '`security?`';
                }
                $out[] = sprintf(
                    '| [#%d](%s) %s%s | %s | %s | %s | %s |',
                    $item['number'],
                    $item['url'],
                    $item['title'],
                    $marks === [] ? '' : ' ' . implode(' ', $marks),
                    $this->area($item['verdict']),
                    $item['verdict']['confidence'],
                    $item['verdict']['suggested_status'] ?? '-',
                    $this->trim($item['verdict']['rationale'], 200)
                );
            }
            $out[] = '';
        }

        if ($others !== []) {
            $out[] = sprintf('## Issues that are not bug reports (%d)', count($others));
            $out[] = '';
            $out[] = 'Real work, but backlog rather than triage — severity does not apply, so '
                . 'they are listed separately instead of padding the bands above.';
            $out[] = '';
            foreach ($others as $item) {
                $out[] = sprintf(
                    '- [#%d](%s) %s — _%s_ — %s',
                    $item['number'],
                    $item['url'],
                    $item['title'],
                    $item['verdict']['kind'],
                    $this->trim($item['verdict']['rationale'], 200)
                );
            }
            $out[] = '';
        }

        $out[] = '## Pull requests by attention needed';
        $out[] = '';
        foreach (['blocking' => 'Blocking', 'soon' => 'Soon', 'routine' => 'Routine'] as $level => $label) {
            $band = array_values(array_filter(
                $prs,
                fn (array $i): bool => ($i['verdict']['attention'] ?? '') === $level
            ));
            $out[] = sprintf('### %s (%d)', $label, count($band));
            $out[] = '';
            if ($band === []) {
                $out[] = '_None._';
                $out[] = '';

                continue;
            }
            $out[] = '| PR | Waiting on | Idle | Why |';
            $out[] = '|---|---|---|---|';
            foreach ($band as $item) {
                $out[] = sprintf(
                    '| [#%d](%s) %s%s | %s | %dd | %s |',
                    $item['number'],
                    $item['url'],
                    $item['title'],
                    empty($item['verdict']['metadata_incomplete']) ? '' : ' `template incomplete`',
                    $item['verdict']['waiting_on'] ?? '-',
                    $item['daysSinceUpdate'],
                    $this->trim($item['verdict']['rationale'], 200)
                );
            }
            $out[] = '';
        }

        $misrouted = array_values(array_filter(
            $prs,
            fn (array $i): bool => !empty($i['verdict']['target_branch_looks_wrong'])
        ));
        if ($misrouted !== []) {
            $out[] = sprintf('## Branch check (%d)', count($misrouted));
            $out[] = '';
            $out[] = 'Labelled a bug fix but opened against `develop`, so the fix would skip '
                . 'the next patch release. Legitimate when the bug only exists in unreleased '
                . 'code — worth a glance, not an alarm.';
            $out[] = '';
            foreach ($misrouted as $item) {
                $out[] = sprintf(
                    '- [#%d](%s) %s — `%s`',
                    $item['number'],
                    $item['url'],
                    $item['title'],
                    $item['baseBranch'] ?? '?'
                );
            }
            $out[] = '';
        }

        $withDuplicates = array_values(array_filter(
            $items,
            fn (array $i): bool => !empty($i['verdict']['duplicate_candidates'])
        ));
        if ($withDuplicates !== []) {
            $out[] = sprintf('## Possible duplicates (%d)', count($withDuplicates));
            $out[] = '';
            $out[] = 'Picked from a keyword shortlist, so these are suggestions rather than '
                . 'matches — and a duplicate sharing no title keywords will not appear here.';
            $out[] = '';
            foreach ($withDuplicates as $item) {
                $numbers = array_map(
                    fn ($n): string => '#' . $n,
                    $item['verdict']['duplicate_candidates']
                );
                $out[] = sprintf(
                    '- [#%d](%s) %s — possibly the same as %s',
                    $item['number'],
                    $item['url'],
                    $item['title'],
                    implode(', ', $numbers)
                );
            }
            $out[] = '';
        }

        $uncertain = array_values(array_filter(
            $items,
            fn (array $i): bool => ($i['verdict']['confidence'] ?? '') === 'low'
        ));
        $out[] = sprintf('## Low confidence — the agent was guessing (%d)', count($uncertain));
        $out[] = '';
        if ($uncertain === []) {
            $out[] = '_None._';
        }
        foreach ($uncertain as $item) {
            $out[] = sprintf(
                '- [#%d](%s) %s — %s',
                $item['number'],
                $item['url'],
                $item['title'],
                $this->trim($item['verdict']['rationale'], 200)
            );
        }
        $out[] = '';

        $out[] = sprintf('## Not classified (%d)', count($skipped));
        $out[] = '';
        if ($skipped === []) {
            $out[] = '_Nothing was left out._';
        } else {
            $out[] = '<details>';
            $out[] = '<summary>Why each item was left out</summary>';
            $out[] = '';
            foreach ($skipped as $entry) {
                $out[] = sprintf('- #%d — %s', $entry['number'], $entry['reason']);
            }
            $out[] = '';
            $out[] = '</details>';
        }
        $out[] = '';

        return implode(PHP_EOL, $out);
    }

    private function trim(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1) . '…';
    }
}
