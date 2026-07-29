<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Tests\TestCase;
use Whilesmart\Agents\Engines\Prism\PrismToolAdapter;
use Whilesmart\Agents\Registries\ModelResourceRegistry;
use Whilesmart\Agents\Registries\ToolRegistry;
use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceField;
use Whilesmart\Agents\Resources\ResourceScope;
use Whilesmart\Agents\Tools\ListResourceTool;
use Whilesmart\Agents\ValueObjects\ToolContext;
use Workbench\App\Models\Currency;
use Workbench\App\Models\Folder;
use Workbench\App\Models\Note;
use Workbench\App\Models\NoteComment;
use Workbench\App\Models\User;

class ModelResourceTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('agents.resources.models', [
            Note::class,
            NoteComment::class,
            Folder::class,
            Currency::class,
        ]);
    }

    /**
     * Invoke a tool the way Prism does, through the adapter closure.
     *
     * @return array<string, mixed>|string
     */
    private function invoke(string $toolName, ToolContext $context, mixed ...$arguments): array|string
    {
        $tool = app(ToolRegistry::class)->resolve($toolName);
        $output = (new PrismToolAdapter)->adapt($tool, $context)->handle(...$arguments);
        $decoded = json_decode($output, true);

        return is_array($decoded) ? $decoded : $output;
    }

    public function test_registry_resolves_configured_models(): void
    {
        $registry = app(ModelResourceRegistry::class);

        $this->assertSame(
            ['notes', 'note_comments', 'folders', 'currencies'],
            $registry->names(),
        );
        $this->assertSame(Note::class, $registry->find('notes')->model);
    }

    public function test_registry_resolves_by_alias(): void
    {
        $registry = app(ModelResourceRegistry::class);

        $this->assertSame('notes', $registry->findByAlias('memos')->name);
        $this->assertNull($registry->findByAlias('nothing'));
    }

    public function test_registering_a_model_without_the_contract_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ModelResourceRegistry::class)->register(User::class);
    }

    public function test_a_read_tool_is_generated_per_resource(): void
    {
        $names = app(ToolRegistry::class)->names();

        foreach (['list_notes', 'list_note_comments', 'list_folders', 'list_currencies'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function test_a_resource_can_opt_out_of_the_generated_tool(): void
    {
        $quiet = new AgentResource(
            name: 'quiet_notes',
            model: Note::class,
            table: 'notes',
            ownerKey: 'user_id',
            readable: [ResourceField::string('title')],
            readTool: false,
        );

        $registry = new ModelResourceRegistry;
        $registry->register($quiet);

        $this->assertSame(['quiet_notes'], $registry->names());
        $this->assertSame([], $registry->toolNames());
    }

    public function test_tool_names_cover_the_generated_tools_only(): void
    {
        $this->assertSame(
            ['list_notes', 'list_note_comments', 'list_folders', 'list_currencies'],
            app(ModelResourceRegistry::class)->toolNames(),
        );
    }

    public function test_description_advertises_the_visible_columns(): void
    {
        $tool = app(ToolRegistry::class)->resolve('list_notes');

        $this->assertStringContainsString('Notes the user has written.', $tool->description());
        $this->assertStringContainsString('title', $tool->description());
        $this->assertStringNotContainsString('user_id', $tool->description());
    }

    public function test_owner_key_scoping_hides_other_users_rows(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 1]);
        Note::create(['user_id' => $bob->id, 'title' => 'b1', 'amount' => 2]);

        $result = $this->invoke('list_notes', ToolContext::forUser($alice));

        $this->assertSame(1, $result['count']);
        $this->assertSame('a1', $result['rows'][0]['title']);
    }

    public function test_internal_columns_are_not_returned(): void
    {
        $alice = $this->createUser();
        Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 1]);

        $result = $this->invoke('list_notes', ToolContext::forUser($alice));

        $this->assertArrayNotHasKey('user_id', $result['rows'][0]);
        $this->assertArrayHasKey('title', $result['rows'][0]);
    }

    public function test_a_guest_gets_nothing_from_an_owned_resource(): void
    {
        $alice = $this->createUser();
        Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 1]);

        $result = $this->invoke('list_notes', ToolContext::guest());

        $this->assertSame('No authenticated user to scope this request to.', $result);
    }

    public function test_rows_come_back_newest_first(): void
    {
        $alice = $this->createUser();
        $older = Note::create(['user_id' => $alice->id, 'title' => 'older', 'amount' => 1]);
        Note::create(['user_id' => $alice->id, 'title' => 'newer', 'amount' => 1]);
        Note::whereKey($older->id)->update(['created_at' => now()->subDay()]);

        $result = $this->invoke('list_notes', ToolContext::forUser($alice));

        $this->assertSame('newer', $result['rows'][0]['title']);
    }

    public function test_limit_is_capped_by_config(): void
    {
        config()->set('agents.resources.max_rows', 2);
        $alice = $this->createUser();

        foreach (range(1, 5) as $i) {
            Note::create(['user_id' => $alice->id, 'title' => "n{$i}", 'amount' => 1]);
        }

        $result = $this->invoke('list_notes', ToolContext::forUser($alice), limit: 100);

        $this->assertSame(2, $result['count']);
    }

    public function test_morph_owner_scoping_uses_both_id_and_type(): void
    {
        $alice = $this->createUser();
        Folder::create(['owner_id' => $alice->id, 'owner_type' => User::class, 'name' => 'mine']);
        Folder::create(['owner_id' => $alice->id, 'owner_type' => Note::class, 'name' => 'not mine']);

        $result = $this->invoke('list_folders', ToolContext::forUser($alice));

        $this->assertSame(1, $result['count']);
        $this->assertSame('mine', $result['rows'][0]['name']);
    }

    public function test_through_scoping_follows_the_parent(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $aliceNote = Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 1]);
        $bobNote = Note::create(['user_id' => $bob->id, 'title' => 'b1', 'amount' => 1]);
        NoteComment::create(['note_id' => $aliceNote->id, 'body' => 'mine']);
        NoteComment::create(['note_id' => $bobNote->id, 'body' => 'theirs']);

        $result = $this->invoke('list_note_comments', ToolContext::forUser($alice));

        $this->assertSame(1, $result['count']);
        $this->assertSame('mine', $result['rows'][0]['body']);
    }

    public function test_through_scoping_refuses_when_the_parent_is_unregistered(): void
    {
        $alice = $this->createUser();
        $note = Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 1]);
        NoteComment::create(['note_id' => $note->id, 'body' => 'mine']);

        $registry = new ModelResourceRegistry;
        $registry->register(NoteComment::class);
        $tool = new ListResourceTool(NoteComment::agentResource(), new ResourceScope($registry));

        $result = $tool->handle([], ToolContext::forUser($alice));

        $this->assertSame('No authenticated user to scope this request to.', $result);
    }

    public function test_a_global_resource_is_readable_by_anyone(): void
    {
        Currency::create(['code' => 'EUR', 'rate' => 1.1]);
        $alice = $this->createUser();

        $result = $this->invoke('list_currencies', ToolContext::forUser($alice));

        $this->assertSame(1, $result['count']);
        $this->assertSame('EUR', $result['rows'][0]['code']);
    }

    public function test_an_unowned_non_global_resource_returns_nothing(): void
    {
        $orphan = new AgentResource(
            name: 'orphans',
            model: Note::class,
            table: 'notes',
            ownerKey: null,
            readable: [ResourceField::string('title')],
        );

        app(ModelResourceRegistry::class)->register($orphan);
        $alice = $this->createUser();
        Note::create(['user_id' => $alice->id, 'title' => 'a1', 'amount' => 1]);

        $tool = new ListResourceTool($orphan, app(ResourceScope::class));
        $result = $tool->handle([], ToolContext::forUser($alice));

        $this->assertSame('No authenticated user to scope this request to.', $result);
    }
}
