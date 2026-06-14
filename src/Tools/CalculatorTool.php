<?php

namespace Whilesmart\Agents\Tools;

use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Deterministic arithmetic. Language models are unreliable at multi-digit math,
 * so financial and other numeric reasoning should route through this tool. Uses
 * a hand-written shunting-yard evaluator (no eval) over + - * / and parentheses.
 */
class CalculatorTool extends AbstractTool
{
    public function name(): string
    {
        return 'calculator';
    }

    public function description(): string
    {
        return 'Evaluate an arithmetic expression with + - * / and parentheses, e.g. "(1200.50 + 300) / 2". Always use this instead of computing arithmetic yourself.';
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('expression', 'The arithmetic expression to evaluate'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $expression = (string) ($arguments['expression'] ?? '');

        try {
            $value = $this->evaluate($expression);
        } catch (\Throwable $e) {
            return 'Could not evaluate expression: '.$e->getMessage();
        }

        return [
            'expression' => $expression,
            'result' => $value,
        ];
    }

    private function evaluate(string $expression): float
    {
        return $this->evaluateRpn($this->toRpn($this->tokenize($expression)));
    }

    /**
     * @return array<int, array{type: string, value: string|float}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $expectOperand = true;

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $number = '';
                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $number .= $expression[$i];
                    $i++;
                }
                $i--;

                if (! is_numeric($number)) {
                    throw new \InvalidArgumentException("invalid number '{$number}'");
                }

                $tokens[] = ['type' => 'num', 'value' => (float) $number];
                $expectOperand = false;

                continue;
            }

            if ($char === '(') {
                $tokens[] = ['type' => 'paren', 'value' => '('];
                $expectOperand = true;

                continue;
            }

            if ($char === ')') {
                $tokens[] = ['type' => 'paren', 'value' => ')'];
                $expectOperand = false;

                continue;
            }

            if (in_array($char, ['+', '-', '*', '/'], true)) {
                if ($char === '-' && $expectOperand) {
                    $tokens[] = ['type' => 'op', 'value' => 'm'];
                } else {
                    $tokens[] = ['type' => 'op', 'value' => $char];
                }
                $expectOperand = true;

                continue;
            }

            throw new \InvalidArgumentException("unexpected character '{$char}'");
        }

        return $tokens;
    }

    /**
     * @param  array<int, array{type: string, value: string|float}>  $tokens
     * @return array<int, array{type: string, value: string|float}>
     */
    private function toRpn(array $tokens): array
    {
        $precedence = ['m' => 4, '*' => 3, '/' => 3, '+' => 2, '-' => 2];
        $rightAssoc = ['m' => true];
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'num') {
                $output[] = $token;
            } elseif ($token['type'] === 'op') {
                while (
                    $operators !== []
                    && end($operators)['type'] === 'op'
                    && (
                        $precedence[end($operators)['value']] > $precedence[$token['value']]
                        || ($precedence[end($operators)['value']] === $precedence[$token['value']] && empty($rightAssoc[$token['value']]))
                    )
                ) {
                    $output[] = array_pop($operators);
                }
                $operators[] = $token;
            } elseif ($token['value'] === '(') {
                $operators[] = $token;
            } elseif ($token['value'] === ')') {
                while ($operators !== [] && end($operators)['value'] !== '(') {
                    $output[] = array_pop($operators);
                }
                if ($operators === []) {
                    throw new \InvalidArgumentException('mismatched parentheses');
                }
                array_pop($operators);
            }
        }

        while ($operators !== []) {
            $operator = array_pop($operators);
            if ($operator['value'] === '(') {
                throw new \InvalidArgumentException('mismatched parentheses');
            }
            $output[] = $operator;
        }

        return $output;
    }

    /**
     * @param  array<int, array{type: string, value: string|float}>  $rpn
     */
    private function evaluateRpn(array $rpn): float
    {
        $stack = [];

        foreach ($rpn as $token) {
            if ($token['type'] === 'num') {
                $stack[] = (float) $token['value'];

                continue;
            }

            if ($token['value'] === 'm') {
                $operand = array_pop($stack) ?? throw new \InvalidArgumentException('malformed expression');
                $stack[] = -$operand;

                continue;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            if ($left === null || $right === null) {
                throw new \InvalidArgumentException('malformed expression');
            }

            $stack[] = match ($token['value']) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0.0 ? throw new \InvalidArgumentException('division by zero') : $left / $right,
                default => throw new \InvalidArgumentException('unknown operator'),
            };
        }

        if (count($stack) !== 1) {
            throw new \InvalidArgumentException('malformed expression');
        }

        return $stack[0];
    }
}
