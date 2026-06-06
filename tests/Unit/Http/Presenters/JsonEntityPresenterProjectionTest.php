<?php

declare(strict_types=1);

namespace BlackParadise\LaravelAdmin\Tests\Unit\Http\Presenters;

use BlackParadise\CoreAdmin\Domain\Contracts\Entity\EntityRecordContract;
use BlackParadise\CoreAdmin\Domain\Contracts\EntityDefinition\EntityDefinitionContract;
use BlackParadise\CoreAdmin\Domain\Entity\EntityRecord;
use BlackParadise\CoreAdmin\Domain\Fields\TextField;
use BlackParadise\CoreAdmin\Domain\Query\PaginatedResult;
use BlackParadise\LaravelAdmin\Http\Presenters\Json\JsonEntityPresenter;
use BlackParadise\LaravelAdmin\Tests\Integration\Fixtures\TestItem;
use BlackParadise\LaravelAdmin\Tests\TestCase;

/**
 * A4 (B4): JsonEntityPresenter must project only the fields in the $fields list.
 *
 * Bug: show/edit/index return record->toArray() which includes ALL model attributes,
 * leaking hashed/hidden columns (e.g. 'password') not declared in the field list.
 *
 * Fix: project the record through the $fields list (keep only keys whose name
 * matches a FieldContract in $fields).
 */
final class JsonEntityPresenterProjectionTest extends TestCase
{
    private JsonEntityPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new JsonEntityPresenter();
    }

    /**
     * Build a minimal EntityDefinitionContract.
     */
    private function makeDefinition(): EntityDefinitionContract
    {
        return new class implements EntityDefinitionContract {
            public function name(): string
            {
                return 'test';
            }
            public function label(): string
            {
                return 'Test';
            }
            public function keyField(): string
            {
                return 'id';
            }
            public function keyType(): string
            {
                return 'int';
            }
            public function modelClass(): string
            {
                return TestItem::class;
            }
            public function fields(): array
            {
                return [];
            }
            public function actions(): array
            {
                return [];
            }
            public function defaultPerPage(): int
            {
                return 15;
            }
            public function searchFields(): array
            {
                return [];
            }
        };
    }

    /**
     * Build an EntityRecord with explicit attributes including a 'password' column
     * that should NOT appear in the presenter output.
     *
     * We use the concrete EntityRecord which implements the full contract.
     *
     * @param array<string, mixed> $attrs
     */
    private function makeRecord(array $attrs): EntityRecordContract
    {
        return new EntityRecord($this->makeDefinition(), $attrs);
    }

    // ------------------------------------------------------------------
    // A4 — show() excludes fields not in $fields list
    // ------------------------------------------------------------------

    /**
     * show() must only expose the field columns declared in $fields.
     * A 'password' column present in toArray() must not appear in the JSON data.
     */
    public function test_show_excludes_fields_not_in_field_list(): void
    {
        $record = $this->makeRecord(['id' => 1, 'name' => 'Bob', 'password' => 'HASH']);
        $fields = [TextField::make('id'), TextField::make('name')];

        $response = $this->presenter->show($record, $fields, $this->makeDefinition());
        $data = json_decode($response->getContent(), true)['data'];

        self::assertArrayHasKey('name', $data);
        self::assertArrayNotHasKey('password', $data);
    }

    // ------------------------------------------------------------------
    // A4 — edit() excludes fields not in $fields list
    // ------------------------------------------------------------------

    public function test_edit_excludes_fields_not_in_field_list(): void
    {
        $record = $this->makeRecord(['id' => 1, 'title' => 'Hello', 'secret_token' => 'TOPSECRET']);
        $fields = [TextField::make('id'), TextField::make('title')];

        $response = $this->presenter->edit($record, $fields, $this->makeDefinition());
        $data = json_decode($response->getContent(), true)['data'];

        self::assertArrayHasKey('title', $data);
        self::assertArrayNotHasKey('secret_token', $data);
    }

    // ------------------------------------------------------------------
    // A4 — index() excludes fields not in $fields list for each row
    // ------------------------------------------------------------------

    public function test_index_excludes_fields_not_in_field_list(): void
    {
        $record1 = $this->makeRecord(['id' => 1, 'name' => 'Alice', 'password' => 'HASH1']);
        $record2 = $this->makeRecord(['id' => 2, 'name' => 'Bob', 'password' => 'HASH2']);

        $fields = [TextField::make('id'), TextField::make('name')];

        $paginated = new PaginatedResult(
            items: [$record1, $record2],
            total: 2,
            page: 1,
            perPage: 15,
        );

        $response = $this->presenter->index($paginated, $fields, $this->makeDefinition());
        $rows = json_decode($response->getContent(), true)['data'];

        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertArrayHasKey('name', $row);
            self::assertArrayNotHasKey('password', $row);
        }
    }
}
