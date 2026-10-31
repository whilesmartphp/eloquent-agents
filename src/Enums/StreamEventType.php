<?php

namespace Whilesmart\Agents\Enums;

/**
 * The kinds of progress a streaming run emits. Provider-agnostic: the engine
 * maps its SDK's own stream events onto these so callers never depend on the
 * underlying SDK.
 */
enum StreamEventType: string
{
    case TextDelta = 'text_delta';
    case Thinking = 'thinking';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
    case StepStart = 'step_start';
    case StepFinish = 'step_finish';
    case Error = 'error';
}
