<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Whilesmart\Agents\Tools\CalculatorTool;
use Whilesmart\Agents\ValueObjects\ToolContext;

class CalculatorToolTest extends TestCase
{
    /**
     * @return array<string, array{string, float}>
     */
    public static function expressions(): array
    {
        return [
            'addition' => ['1 + 2', 3.0],
            'precedence' => ['2 + 3 * 4', 14.0],
            'parentheses' => ['(2 + 3) * 4', 20.0],
            'decimals' => ['1200.50 + 300', 1500.50],
            'division' => ['10 / 4', 2.5],
            'unary minus' => ['-5 + 2', -3.0],
            'nested' => ['((1 + 2) * (3 + 4))', 21.0],
        ];
    }

    #[DataProvider('expressions')]
    public function test_evaluates_expressions(string $expression, float $expected): void
    {
        $result = (new CalculatorTool)->handle(['expression' => $expression], ToolContext::guest());

        $this->assertSame($expected, $result['result']);
    }

    public function test_division_by_zero_is_reported(): void
    {
        $result = (new CalculatorTool)->handle(['expression' => '1 / 0'], ToolContext::guest());

        $this->assertStringContainsString('division by zero', $result);
    }

    public function test_rejects_unsafe_input(): void
    {
        $result = (new CalculatorTool)->handle(['expression' => 'phpinfo()'], ToolContext::guest());

        $this->assertStringContainsString('Could not evaluate', $result);
    }
}
