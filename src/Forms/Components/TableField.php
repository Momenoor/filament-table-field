<?php

namespace Momenoor\FilamentTableField\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * A custom Filament form field that renders an editable table
 * for managing related HasMany or BelongsToMany records.
 */
class TableField extends Field implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament-table-field::forms.components.table-field';

    // Action toggles
    protected bool|Closure $isCreateDisabled = false;
    protected bool|Closure $isEditDisabled = false;
    protected bool|Closure $isDeleteDisabled = false;

    // Lifecycle hooks
    protected ?Closure $beforeCreateRecord = null;
    protected ?Closure $beforeUpdateRecord = null;

    // Default and configuration options
    protected array|Closure $defaultRecordData = [];
    protected string|Closure|null $tableHeading = null;

    // Table rendering config
    protected ?array $tableColumns = [];
    protected string|Closure|null $relationship = null;
    protected array|Closure $createFormSchema = [];
    protected array|Closure $headerActions = [];
    protected array $actions = [];
    protected string|Closure|null $emptyStateHeading = null;
    protected string|Closure|null $emptyStateDescription = null;
    protected string|Closure|null $emptyStateIcon = null;

    /**
     * Component initialization setup.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(fn () => $this->hydrateRelationshipState());
        $this->dehydrated(false);

        $this->dehydrateStateUsing(function (?array $state): array {
            $this->syncRelationship();
            return $state ?? [];
        });
    }

    // Configuration API

    public function disableCreate(bool|Closure $condition = true): static
    {
        $this->isCreateDisabled = $condition;
        return $this;
    }

    public function disableEdit(bool|Closure $condition = true): static
    {
        $this->isEditDisabled = $condition;
        return $this;
    }

    public function disableDelete(bool|Closure $condition = true): static
    {
        $this->isDeleteDisabled = $condition;
        return $this;
    }

    public function relationship(string|Closure $name): static
    {
        $this->relationship = $name;
        return $this;
    }

    public function tableColumns(array|Closure $columns): static
    {
        $this->tableColumns = $columns;
        return $this;
    }

    public function createFormSchema(array|Closure $schema): static
    {
        $this->createFormSchema = $schema;
        return $this;
    }

    public function headerActions(array|Closure $actions): static
    {
        $this->headerActions = $actions;
        return $this;
    }

    public function actions(array|Closure $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    public function emptyStateHeading(string|Closure|null $heading): static
    {
        $this->emptyStateHeading = $heading;
        return $this;
    }

    public function emptyStateDescription(string|Closure|null $description): static
    {
        $this->emptyStateDescription = $description;
        return $this;
    }

    public function emptyStateIcon(string|Closure|null $icon): static
    {
        $this->emptyStateIcon = $icon;
        return $this;
    }

    public function heading(string|Closure $heading): static
    {
        $this->tableHeading = $heading;
        return $this;
    }

    public function defaultRecordDefaults(array|Closure $defaults): static
    {
        $this->defaultRecordData = $defaults;
        return $this;
    }

    public function beforeCreateRecord(Closure $callback): static
    {
        $this->beforeCreateRecord = $callback;
        return $this;
    }

    public function beforeUpdateRecord(Closure $callback): static
    {
        $this->beforeUpdateRecord = $callback;
        return $this;
    }

    // Hook resolver

    protected function callHook(Closure $callback, array $data): array
    {
        $result = app()->call($callback, [
            'data' => $data,
            'state' => $this->getState(),
            'parentState' => $this->getLivewire()->getForm()->getState(),
            'record' => $this->getRecord(),
            'field' => $this,
        ]);

        return is_array($result) ? array_merge($data, $result) : $data;
    }

    // Runtime evaluation helpers

    protected function getDefaultRecordData(): array
    {
        return $this->evaluate($this->defaultRecordData) ?? [];
    }

    protected function isCreateDisabled(): bool
    {
        return (bool) $this->evaluate($this->isCreateDisabled);
    }

    protected function isEditDisabled(): bool
    {
        return (bool) $this->evaluate($this->isEditDisabled);
    }

    protected function isDeleteDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDeleteDisabled);
    }

    // Filament Table

    public function getTable(): Table
    {
        return Table::make()
            ->heading($this->evaluate($this->tableHeading))
            ->query(fn (): Collection => collect($this->getState())->map(fn ($item) => (object) $item))
            ->columns($this->evaluate($this->tableColumns))
            ->headerActions(array_merge($this->getDefaultHeaderActions(), $this->evaluate($this->headerActions)))
            ->actions(array_merge($this->getDefaultActions(), $this->evaluate($this->actions)))
            ->emptyStateHeading($this->evaluate($this->emptyStateHeading))
            ->emptyStateDescription($this->evaluate($this->emptyStateDescription))
            ->emptyStateIcon($this->evaluate($this->emptyStateIcon));
    }

    protected function getDefaultHeaderActions(): array
    {
        if ($this->isCreateDisabled()) return [];

        return [
            Action::make('create')
                ->form($this->evaluate($this->createFormSchema))
                ->action(fn (array $data) => $this->createRecord($data)),
        ];
    }

    protected function getDefaultActions(): array
    {
        $actions = [];

        if (! $this->isEditDisabled()) {
            $actions[] = Action::make('edit')
                ->form($this->evaluate($this->createFormSchema))
                ->mountUsing(fn(array $arguments) => $this->form->fill($arguments['record']))
                ->action(fn (array $data, array $arguments) =>
                $this->updateRecord($arguments['record']['id'] ?? $arguments['record']['_temp_id'], $data)
                );
        }

        if (! $this->isDeleteDisabled()) {
            $actions[] = Action::make('delete')
                ->action(fn (array $arguments) =>
                $this->deleteRecord($arguments['record']['id'] ?? $arguments['record']['_temp_id'])
                );
        }

        return $actions;
    }

    // Record operations

    protected function createRecord(array $data): void
    {
        $data = $this->beforeCreateRecord instanceof Closure
            ? $this->callHook($this->beforeCreateRecord, $data)
            : $data;

        $record = array_merge($this->getDefaultRecordData(), $data, ['_temp_id' => uniqid()]);

        $this->state([...$this->getState(), $record]);
        $this->callAfterStateUpdated();
    }

    protected function updateRecord(int|string $key, array $data): void
    {
        $data = $this->beforeUpdateRecord instanceof Closure
            ? $this->callHook($this->beforeUpdateRecord, $data)
            : $data;

        $this->state(
            collect($this->getState())
                ->map(fn($record) => ($record['id'] ?? $record['_temp_id']) === $key
                    ? array_merge($record, $data)
                    : $record
                )
                ->toArray()
        );

        $this->callAfterStateUpdated();
    }

    protected function deleteRecord(int|string $key): void
    {
        $this->state(
            collect($this->getState())
                ->reject(fn($record) => ($record['id'] ?? $record['_temp_id']) === $key)
                ->values()
                ->all()
        );

        $this->callAfterStateUpdated();
    }

    // Relationship sync

    protected function hydrateRelationshipState(): void
    {
        $relationship = $this->getRelationship();

        $this->state($relationship ? $relationship->get()->toArray() : []);
    }

    protected function getRelationship(): BelongsToMany|HasMany|null
    {
        $model = $this->getRecord();
        $relationName = $this->evaluate($this->relationship);

        if (! $model || ! $relationName || ! method_exists($model, $relationName)) {
            return null;
        }

        $relation = $model->{$relationName}();

        return $relation instanceof BelongsToMany || $relation instanceof HasMany ? $relation : null;
    }

    protected function syncRelationship(): void
    {
        $relationship = $this->getRelationship();
        if (! $relationship) return;

        $currentItems = $relationship->get();
        $newState = collect($this->getState());

        match (true) {
            $relationship instanceof BelongsToMany => $this->syncManyToMany($relationship, $currentItems, $newState),
            $relationship instanceof HasMany => $this->syncOneToMany($relationship, $currentItems, $newState),
        };
    }

    protected function syncManyToMany(BelongsToMany $relationship, Collection $currentItems, Collection $newState): void
    {
        $currentIds = $currentItems->pluck('id')->all();
        $newIds = $newState->pluck('id')->filter()->all();

        if ($toDetach = array_diff($currentIds, $newIds)) {
            $relationship->detach($toDetach);
        }

        $relationship->sync(
            $newState->mapWithKeys(fn($record) => [
                    $record['id'] ?? $record['_temp_id'] => Arr::except($record, ['id', '_temp_id']),
            ])->all()
        );
    }

    protected function syncOneToMany(HasMany $relationship, Collection $currentItems, Collection $newState): void
    {
        $currentIds = $currentItems->pluck('id')->all();
        $newIds = $newState->pluck('id')->filter()->all();

        if ($toDelete = array_diff($currentIds, $newIds)) {
            $relationship->getRelated()::whereIn('id', $toDelete)->delete();
        }

        $newState->each(function ($record) use ($relationship) {
            $data = Arr::except($record, ['id', '_temp_id']);
            if (isset($record['id'])) {
                $relationship->getRelated()::whereKey($record['id'])->update($data);
            } else {
                $relationship->create($data);
            }
        });
    }

    // Multilingual support
    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return $this->getTable()->makeTranslatableContentDriver();
    }
}
