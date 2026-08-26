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

    private const SEVERITY_ICON = [
        'Critical' => ':red_circle:',
        'Major' => ':large_orange_circle:',
        'Minor' => ':large_yellow_circle:',
        'Trivial' => ':white_circle:',
    ];

    /**
     * Items shown per band before the rest is rolled up. The overflow is always
     * announced - a silently truncated list reads as "that was everything".
     */
    private const PER_BAND = 8;

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
    public function renderSlack(array $items, string $since, string $until): string
    {
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
                    $this->trim($entry['item']['title'], 80),
                    implode(', ', $entry['reasons'])
                );
            }
            $lines[] = $this->overflow(count($flagged));
        }

        foreach (self::SEVERITIES as $severity) {
            $band = array_values(array_filter(
                $bugs,
                fn (array $i): bool => ($i['verdict']['severity'] ?? '') === $severity
            ));
            if ($band === []) {
                continue;
            }
            $lines[] = '';
            $lines[] = sprintf('*Issues · proposed %s (%d)*', $severity, count($band));
            foreach (array_slice($band, 0, self::PER_BAND) as $item) {
                $marks = [];
                if (!empty($item['verdict']['looks_like_regression'])) {
                    $marks[] = 'regression';
                }
                if (!empty($item['verdict']['security_suspicion'])) {
                    $marks[] = 'security?';
                }
                $lines[] = sprintf(
                    '%s <%s|#%d> %s%s — _%s_',
                    self::SEVERITY_ICON[$severity],
                    $item['url'],
                    $item['number'],
                    $this->trim($item['title'], 80),
                    $marks === [] ? '' : ' `' . implode('` `', $marks) . '`',
                    $this->trim($item['verdict']['rationale'], 110)
                );
            }
            $lines[] = $this->overflow(count($band));
        }

        foreach (['blocking' => 'Blocking', 'soon' => 'Soon'] as $level => $label) {
            $band = array_values(array_filter(
                $prs,
                fn (array $i): bool => ($i['verdict']['attention'] ?? '') === $level
            ));
            if ($band === []) {
                continue;
            }
            $lines[] = '';
            $lines[] = sprintf('*Pull requests · %s (%d)*', $label, count($band));
            foreach (array_slice($band, 0, self::PER_BAND) as $item) {
                $lines[] = sprintf(
                    '<%s|#%d> %s — waiting on *%s*, %dd idle',
                    $item['url'],
                    $item['number'],
                    $this->trim($item['title'], 80),
                    $item['verdict']['waiting_on'] ?? 'unknown',
                    $item['daysSinceUpdate']
                );
            }
            $lines[] = $this->overflow(count($band));
        }

        return implode(PHP_EOL, array_filter($lines, fn (string $l): bool => $l !== "\0"));
    }

    private function overflow(int $total): string
    {
        return $total > self::PER_BAND
            ? sprintf('_+ %d more, see the workflow run_', $total - self::PER_BAND)
            : "\0";
    }

    private function trim(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1) . '…';
    }
}
