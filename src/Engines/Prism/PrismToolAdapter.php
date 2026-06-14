<?php

namespace Whilesmart\Agents\Engines\Prism;

use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Tool as PrismTool;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Engines\Prism\Contracts\ProvidesPrismTool;
use Whilesmart\Agents\Enums\ParameterType;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Translates a package Tool into a Prism tool bound to a context. This is the
 * one place that maps our parameter specs onto Prism's schema API and wraps
 * handle()/authorize() into Prism's calling convention.
 */
class PrismToolAdapter
{
    public function adapt(Tool $tool, ToolContext $context): PrismTool
    {
        if ($tool instanceof ProvidesPrismTool) {
            return $tool->toPrismTool($context);
        }

        $prismTool = (new PrismTool)
            ->as($tool->name())
            ->for($tool->description());

        foreach ($tool->parameters() as $parameter) {
            $this->applyParameter($prismTool, $parameter);
        }

        $prismTool->using(function (...$arguments) use ($tool, $context): string {
            $arguments = $this->normalizeArguments($tool, $arguments);

            if (! $tool->authorize($context)) {
                return $this->deniedMessage();
            }

            return $this->stringify($tool->handle($arguments, $context));
        });

        return $prismTool;
    }

    protected function applyParameter(PrismTool $prismTool, ParameterSpec $parameter): void
    {
        match ($parameter->type) {
            ParameterType::STRING => $prismTool->withStringParameter($parameter->name, $parameter->description, $parameter->required),
            ParameterType::NUMBER => $prismTool->withNumberParameter($parameter->name, $parameter->description, $parameter->required),
            ParameterType::BOOLEAN => $prismTool->withBooleanParameter($parameter->name, $parameter->description, $parameter->required),
            ParameterType::ENUM => $prismTool->withEnumParameter($parameter->name, $parameter->description, $parameter->options, $parameter->required),
            ParameterType::ARRAY => $prismTool->withArrayParameter($parameter->name, $parameter->description, $this->itemsSchema($parameter->itemType), $parameter->required),
        };
    }

    protected function itemsSchema(ParameterType $itemType): StringSchema|NumberSchema|BooleanSchema
    {
        return match ($itemType) {
            ParameterType::NUMBER => new NumberSchema('item', 'A list item'),
            ParameterType::BOOLEAN => new BooleanSchema('item', 'A list item'),
            default => new StringSchema('item', 'A list item'),
        };
    }

    protected function deniedMessage(): string
    {
        return 'This action is not permitted for the current user.';
    }

    /**
     * @param  string|array<mixed>  $result
     */
    protected function stringify(string|array $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /**
     * Prism passes tool-call arguments as named arguments, which a variadic
     * closure collects by key. Guard against positional delivery by zipping
     * against the declared parameter order.
     *
     * @param  array<int|string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function normalizeArguments(Tool $tool, array $arguments): array
    {
        if ($arguments === [] || ! array_is_list($arguments)) {
            /** @var array<string, mixed> $arguments */
            return $arguments;
        }

        $names = array_map(fn (ParameterSpec $p): string => $p->name, $tool->parameters());
        $normalized = [];

        foreach ($arguments as $index => $value) {
            if (isset($names[$index])) {
                $normalized[$names[$index]] = $value;
            }
        }

        return $normalized;
    }
}
