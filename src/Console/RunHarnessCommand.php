<?php

namespace Whilesmart\Agents\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Agents\AgentManager;
use Whilesmart\Agents\Exceptions\HarnessNotFoundException;
use Whilesmart\Agents\ValueObjects\ToolContext;

class RunHarnessCommand extends Command
{
    protected $signature = 'agents:run {harness : The harness name} {input : The user message} {--user= : Act as this user id}';

    protected $description = 'Run a harness against an input and print the result (calls a live provider)';

    public function handle(AgentManager $agents): int
    {
        $context = $this->resolveContext();

        if ($context === null) {
            return self::FAILURE;
        }

        try {
            $result = $agents->run($this->argument('harness'), $this->argument('input'), $context);
        } catch (HarnessNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result->ok) {
            $this->error('Run failed: '.$result->error);

            return self::FAILURE;
        }

        $this->line($result->text);
        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['steps', $result->steps],
            ['tool calls', implode(', ', array_column($result->toolCalls, 'name')) ?: '-'],
            ['prompt tokens', $result->usage['prompt_tokens']],
            ['completion tokens', $result->usage['completion_tokens']],
        ]);

        return self::SUCCESS;
    }

    protected function resolveContext(): ?ToolContext
    {
        $userId = $this->option('user');

        if ($userId === null) {
            return ToolContext::guest();
        }

        /** @var class-string<Model>|null $model */
        $model = config('auth.providers.users.model');

        if ($model === null) {
            $this->error('No user model configured (auth.providers.users.model).');

            return null;
        }

        $user = $model::find($userId);

        if ($user === null) {
            $this->error("User [{$userId}] not found.");

            return null;
        }

        return ToolContext::forUser($user);
    }
}
