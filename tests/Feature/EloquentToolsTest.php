<?php

namespace Tests\Feature;

use Tests\TestCase;
use Whilesmart\Agents\Engines\Prism\PrismToolAdapter;
use Whilesmart\Agents\Tools\EloquentQueryTool;
use Whilesmart\Agents\Tools\EloquentWriteTool;
use Whilesmart\Agents\ValueObjects\ToolContext;
use Workbench\App\Models\Note;

class EloquentToolsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('agents.eloquent.models', [
            'notes' => [
                'model' => Note::class,
                'owner_key' => 'user_id',
                'readable' => ['id', 'title', 'amount'],
                'writable' => ['title', 'body', 'amount'],
            ],
        ]);
    }

    /**
     * Invoke a tool the way Prism does: through the adapter closure, which runs
     * authorization. Returns the decoded array (tools emit JSON strings).
     *
     * @return array<string, mixed>|string
     */
    private function invoke(object $tool, ToolContext $context, mixed ...$arguments): array|string
    {
        $output = (new PrismToolAdapter)->adapt($tool, $context)->handle(...$arguments);
        $decoded = json_decode($output, true);

        return is_array($decoded) ? $decoded : $output;
    }

    public function test_query_is_owner_scoped(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 10]);
        Note::create(['user_id' => $alice->id, 'title' => 'a2', 'amount' => 20]);
        Note::create(['user_id' => $bob->id, 'title' => 'b1', 'amount' => 99]);

        $result = $this->invoke(new EloquentQueryTool, ToolContext::forUser($alice), resource: 'notes', select: 'list');

        $this->assertCount(2, $result['rows']);
    }

    public function test_query_aggregates(): void
    {
        $alice = $this->createUser();
        Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 10]);
        Note::create(['user_id' => $alice->id, 'title' => 'a2', 'amount' => 20]);

        $result = $this->invoke(new EloquentQueryTool, ToolContext::forUser($alice), resource: 'notes', select: 'sum', column: 'amount');

        $this->assertEquals(30, $result['value']);
    }

    public function test_query_rejects_unlisted_resource(): void
    {
        $alice = $this->createUser();

        $result = $this->invoke(new EloquentQueryTool, ToolContext::forUser($alice), resource: 'secrets', select: 'list');

        $this->assertSame("Resource 'secrets' is not available.", $result);
    }

    public function test_write_is_denied_by_default(): void
    {
        $alice = $this->createUser();

        $result = $this->invoke(
            new EloquentWriteTool,
            ToolContext::forUser($alice),
            resource: 'notes',
            operation: 'create',
            values: json_encode(['title' => 'x']),
        );

        $this->assertSame('This action is not permitted for the current user.', $result);
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_write_create_when_enabled(): void
    {
        config()->set('agents.eloquent.allow_writes', true);
        $alice = $this->createUser();

        $result = $this->invoke(
            new EloquentWriteTool,
            ToolContext::forUser($alice),
            resource: 'notes',
            operation: 'create',
            values: json_encode(['title' => 'Groceries', 'amount' => 42]),
        );

        $this->assertSame('create', $result['operation']);
        $this->assertDatabaseHas('notes', ['user_id' => $alice->id, 'title' => 'Groceries']);
    }

    public function test_write_ignores_non_writable_columns(): void
    {
        config()->set('agents.eloquent.allow_writes', true);
        $alice = $this->createUser();

        $this->invoke(
            new EloquentWriteTool,
            ToolContext::forUser($alice),
            resource: 'notes',
            operation: 'create',
            values: json_encode(['title' => 'x', 'user_id' => 999999]),
        );

        $this->assertDatabaseHas('notes', ['title' => 'x', 'user_id' => $alice->id]);
        $this->assertDatabaseMissing('notes', ['user_id' => 999999]);
    }

    public function test_write_update_is_owner_scoped(): void
    {
        config()->set('agents.eloquent.allow_writes', true);
        $alice = $this->createUser();
        $bob = $this->createUser();
        $note = Note::create(['user_id' => $alice->id, 'title' => 'alice note', 'amount' => 1]);

        $result = $this->invoke(
            new EloquentWriteTool,
            ToolContext::forUser($bob),
            resource: 'notes',
            operation: 'update',
            id: $note->id,
            values: json_encode(['title' => 'hijacked']),
        );

        $this->assertSame('Record not found.', $result);
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'title' => 'alice note']);
    }
}
