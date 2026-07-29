<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;
use Workbench\App\Models\Currency;
use Workbench\App\Models\Folder;
use Workbench\App\Models\Note;
use Workbench\App\Models\NoteComment;
use Workbench\App\Models\User;

class ExportSchemaCommandTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function export(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'schema').'.yml';

        $this->artisan('agents:export-schema', ['--output' => $path])->assertSuccessful();

        $schema = Yaml::parseFile($path);
        unlink($path);

        return $schema;
    }

    public function test_every_resource_becomes_an_entity(): void
    {
        $entities = $this->export()['semantic_layer']['entities'];

        $this->assertSame(
            ['notes', 'note_comments', 'folders', 'currencies'],
            array_keys($entities),
        );
        $this->assertSame('notes', $entities['notes']['table']);
        $this->assertSame(['memos'], $entities['notes']['aliases']);
    }

    public function test_label_columns_are_carried_over(): void
    {
        $entities = $this->export()['semantic_layer']['entities'];

        $this->assertSame('title', $entities['notes']['label_column']);
        $this->assertArrayNotHasKey('label_column', $entities['note_comments']);
    }

    public function test_columns_carry_type_key_and_visibility(): void
    {
        $columns = $this->export()['semantic_layer']['entities']['notes']['columns'];

        $this->assertTrue($columns['id']['primary']);
        $this->assertSame('integer', $columns['id']['type']);
        $this->assertSame('decimal', $columns['amount']['type']);
        $this->assertSame('datetime', $columns['created_at']['type']);
        $this->assertTrue($columns['user_id']['hidden']);
        $this->assertArrayNotHasKey('hidden', $columns['title']);
    }

    public function test_foreign_keys_carry_their_reference(): void
    {
        $columns = $this->export()['semantic_layer']['entities']['note_comments']['columns'];

        $this->assertSame('notes.id', $columns['note_id']['references']);
    }

    public function test_relationships_are_exported(): void
    {
        $relationships = $this->export()['semantic_layer']['relationships'];
        $names = array_column($relationships, 'name');

        $this->assertContains('note_comments', $names);
        $this->assertContains('comment_note', $names);

        $hasMany = $relationships[array_search('note_comments', $names, true)];
        $this->assertSame('one_to_many', $hasMany['type']);
        $this->assertSame('notes', $hasMany['from']);
        $this->assertSame('note_id', $hasMany['foreign_key']);
    }

    public function test_allowed_tables_covers_every_entity(): void
    {
        $schema = $this->export();
        $tables = $schema['security']['allowed_tables'];

        foreach ($schema['semantic_layer']['entities'] as $entity) {
            $this->assertContains($entity['table'], $tables);
        }
    }

    public function test_owner_key_becomes_a_required_filter(): void
    {
        $filters = $this->export()['security']['required_filters'];

        $this->assertSame('user_id', $filters['notes']['column']);
        $this->assertArrayNotHasKey('param', $filters['notes']);
    }

    public function test_a_renamed_owner_param_is_emitted(): void
    {
        $filters = $this->export()['security']['required_filters'];

        $this->assertSame('owner_id', $filters['folders']['column']);
        $this->assertSame('user_id', $filters['folders']['param']);
        $this->assertSame(['owner_type' => User::class], $filters['folders']['constants']);
    }

    public function test_through_scoping_becomes_a_through_filter(): void
    {
        $filters = $this->export()['security']['required_filters'];

        $this->assertSame(
            ['column' => 'note_id', 'references' => 'notes.id'],
            $filters['note_comments']['through'],
        );
    }

    public function test_bypass_roles_are_carried_into_the_filter(): void
    {
        $filters = $this->export()['security']['required_filters'];

        $this->assertSame(['admin'], $filters['notes']['bypass_roles'] ?? null);
        $this->assertArrayNotHasKey('bypass_roles', $filters['folders']);
    }

    public function test_a_resource_without_a_generated_tool_is_still_exported(): void
    {
        $entities = $this->export()['semantic_layer']['entities'];

        // note_comments opts into a tool; the point is that the schema does not
        // depend on one, so a hand-written tool does not cost a schema entry.
        $this->assertArrayHasKey('note_comments', $entities);
    }

    public function test_a_global_resource_has_no_required_filter(): void
    {
        $filters = $this->export()['security']['required_filters'];

        $this->assertArrayNotHasKey('currencies', $filters);
    }

    public function test_every_filtered_table_is_allowed(): void
    {
        $schema = $this->export();

        foreach (array_keys($schema['security']['required_filters']) as $table) {
            $this->assertContains($table, $schema['security']['allowed_tables']);
        }
    }

    public function test_an_unknown_format_is_rejected(): void
    {
        $this->artisan('agents:export-schema', ['--format' => 'graphql'])->assertFailed();
    }

    public function test_no_resources_is_reported_not_crashed(): void
    {
        config()->set('agents.resources.models', []);
        $this->refreshApplication();

        $this->artisan('agents:export-schema')->assertSuccessful();
    }
}
