<?php

namespace Console\App\Service;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * Thin wrapper over the Claude Messages API for classification work.
 *
 * Every call here is one item in, one JSON verdict out. There is no
 * conversation and no tool use: the rubric lives in the system prompt, the item
 * in the user message, and a structured-output schema guarantees the shape of
 * the answer so no caller has to parse free text.
 */
class Anthropic
{
    public const MODEL = 'claude-opus-5';

    /**
     * A verdict is a handful of short fields. This only has to leave room for
     * adaptive thinking plus the JSON object.
     */
    private const MAX_TOKENS = 4000;

    /**
     * Applying a written rubric to a report is matching, not open-ended
     * reasoning, so medium effort is the right trade.
     */
    private const EFFORT = 'medium';

    private const MAX_ATTEMPTS = 3;

    /**
     * @var Client|null
     */
    protected $client;

    /**
     * @var array{input: int, output: int, cacheWrite: int, cacheRead: int}
     */
    protected $usage = [
        'input' => 0,
        'output' => 0,
        'cacheWrite' => 0,
        'cacheRead' => 0,
    ];

    public function __construct(?string $apiKey = null)
    {
        if (!empty($apiKey)) {
            $this->client = new Client(apiKey: $apiKey);
        }
    }

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    /**
     * Build the system prompt as a single cacheable block.
     *
     * The rubric is byte-identical for every item in a run, so one breakpoint
     * at the end of it turns N full-price prompt reads into one. Anything that
     * varies per item must stay in the user message: put it here and the cache
     * is invalidated on every single call.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cachedSystem(string $prompt): array
    {
        return [
            [
                'type' => 'text',
                'text' => $prompt,
                'cacheControl' => ['type' => 'ephemeral', 'ttl' => '1h'],
            ],
        ];
    }

    /**
     * Classify one item and return the validated verdict.
     *
     * @param array<int, array<string, mixed>> $system
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function classify(array $system, array $schema, string $userText): array
    {
        if ($this->client === null) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured');
        }

        $lastError = null;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $message = $this->client->messages->create(
                    maxTokens: self::MAX_TOKENS,
                    messages: [['role' => 'user', 'content' => $userText]],
                    model: self::MODEL,
                    outputConfig: [
                        'effort' => self::EFFORT,
                        'format' => ['type' => 'json_schema', 'schema' => $schema],
                    ],
                    system: $system,
                    thinking: ['type' => 'adaptive'],
                );
            } catch (RateLimitException|APIConnectionException|InternalServerException $e) {
                // Transient: throttling, a dropped connection, a 5xx. Worth
                // another go after backing off.
                $lastError = $e;
                // No point sleeping before giving up on the final attempt.
                if ($attempt < self::MAX_ATTEMPTS - 1) {
                    sleep(5 * (2 ** $attempt));
                }

                continue;
            } catch (APIStatusException $e) {
                // Anything else the API rejected is our bug - a malformed
                // schema, a bad model id, a missing key. Retrying repeats it.
                throw new \RuntimeException('API rejected the request: ' . $e->getMessage(), 0, $e);
            }

            if ($message->stopReason === 'refusal') {
                throw new \RuntimeException('Model declined to answer this item');
            }

            $this->recordUsage($message);

            return $this->extractJson($message);
        }

        throw new \RuntimeException('Gave up after ' . self::MAX_ATTEMPTS . ' attempts: ' . ($lastError ? $lastError->getMessage() : 'unknown error'));
    }

    /**
     * @return array{input: int, output: int, cacheWrite: int, cacheRead: int}
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    /**
     * Cost of the run at published list prices.
     *
     * Cache reads bill at a tenth of the input rate and writes at 1.25x, which
     * is why a well-cached run costs far less than the raw input count suggests.
     */
    public function getEstimatedCost(): float
    {
        $inputPerMTok = 5.00;
        $outputPerMTok = 25.00;

        return (
            $this->usage['input'] * $inputPerMTok
            + $this->usage['cacheWrite'] * $inputPerMTok * 1.25
            + $this->usage['cacheRead'] * $inputPerMTok * 0.1
            + $this->usage['output'] * $outputPerMTok
        ) / 1000000;
    }

    /**
     * @param mixed $message
     */
    protected function recordUsage($message): void
    {
        $usage = $message->usage;
        $this->usage['input'] += $usage->inputTokens ?? 0;
        $this->usage['output'] += $usage->outputTokens ?? 0;
        $this->usage['cacheWrite'] += $usage->cacheCreationInputTokens ?? 0;
        $this->usage['cacheRead'] += $usage->cacheReadInputTokens ?? 0;
    }

    /**
     * Pull the structured object out of the response.
     *
     * Always goes through a JSON decode: escaping inside structured output can
     * vary, so no caller should ever match on the serialised string.
     *
     * @param mixed $message
     *
     * @return array<string, mixed>
     */
    protected function extractJson($message): array
    {
        foreach ($message->content as $block) {
            if (($block->type ?? null) !== 'text') {
                continue;
            }
            $decoded = json_decode($block->text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('No JSON object found in the response');
    }
}
