<?php

use Console\App\Service\Triage\Prompts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PromptsTest extends TestCase
{
    /**
     * Keywords the structured-output schema validator rejects.
     *
     * A schema carrying one of these passes every local check and is then
     * refused by the API on every single item, which surfaces as a run that
     * scores nothing rather than as an obvious error. That is exactly how
     * `maxItems` shipped: `maxLength` had already been removed for this reason
     * and the array constraint was missed.
     *
     * @var array<int, string>
     */
    private const UNSUPPORTED = [
        'format',
        'maxItems',
        'maxLength',
        'maximum',
        'minItems',
        'minLength',
        'minimum',
        'pattern',
        'uniqueItems',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function schemaProvider(): array
    {
        return ['issue' => ['issue'], 'pull request' => ['pull_request']];
    }

    #[DataProvider('schemaProvider')]
    public function testSchemaUsesOnlySupportedKeywords(string $kind): void
    {
        $offenders = [];
        $this->collect(Prompts::schema($kind), $kind, $offenders);

        $this->assertSame([], $offenders, sprintf(
            'The %s schema uses keywords structured outputs reject: %s',
            $kind,
            implode(', ', $offenders)
        ));
    }

    #[DataProvider('schemaProvider')]
    public function testEveryRequiredPropertyIsDefined(string $kind): void
    {
        $schema = Prompts::schema($kind);

        foreach ($schema['required'] as $property) {
            $this->assertArrayHasKey(
                $property,
                $schema['properties'],
                sprintf('%s.%s is required but never defined', $kind, $property)
            );
        }
    }

    #[DataProvider('schemaProvider')]
    public function testSchemaIsClosed(string $kind): void
    {
        $this->assertFalse(
            Prompts::schema($kind)['additionalProperties'] ?? true,
            $kind . ' schema must set additionalProperties to false'
        );
    }

    public function testRubricsCarryTheirSubstance(): void
    {
        $severity = Prompts::severity();

        $this->assertStringContainsString('Critical', $severity);
        $this->assertStringContainsString('Worked examples', $severity, 'mined examples are missing');
        $this->assertStringContainsString('waiting_on', Prompts::pullRequest());
    }

    /**
     * @param mixed $node
     * @param array<int, string> $offenders
     */
    private function collect($node, string $path, array &$offenders): void
    {
        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::UNSUPPORTED, true)) {
                $offenders[] = $path . '.' . $key;
            }
            $this->collect($value, $path . '.' . (is_string($key) ? $key : '[]'), $offenders);
        }
    }
}
