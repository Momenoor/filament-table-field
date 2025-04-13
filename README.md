# Filament Table Field

[![Latest Version](https://img.shields.io/packagist/v/momenoor/filament-table-field.svg)](https://packagist.org/packages/momenoor/filament-table-field)

# Filament Table Field

A powerful custom Laravel Filament form field that renders an interactive table to manage related records via `HasMany` or `BelongsToMany` relationships — directly within your form.

> Built for Filament v3 — no Blade components needed.

---

## 🚀 Features

- Inline editable table using Filament Tables API
- Modal-based Create/Edit/Delete actions
- Supports `HasMany` and `BelongsToMany` relationships
- Auto-syncs records after form submission
- Dynamic form schema and column config
- Lifecycle hooks:
    - `beforeCreateRecord()`
    - `beforeUpdateRecord()`
- Auto-injection of parent form data
- Configurable header actions and table display options
- Customizable empty state


---

## 📦 Installation

```bash
composer require momenoor/filament-table-field
```

Register the plugin (if not auto-discovered):

```php
\Momenoor\FilamentTableField\FilamentTableFieldServiceProvider::class,
```

---

## 🔧 Usage

```php
use Momenoor\FilamentTableField\Forms\Components\TableField;

TableField::make('tasks')
    ->relationship('tasks')
    ->tableColumns([
        TextColumn::make('name'),
        TextColumn::make('status')->badge(),
    ])
    ->createFormSchema([
        TextInput::make('name')->required(),
        Select::make('status')->options([
            'new' => 'New',
            'in_progress' => 'In Progress',
            'done' => 'Done',
        ])->required(),
    ])
    ->beforeCreateRecord(fn ($parentState) => [
        'client_id' => $parentState['client_id'] ?? null,
    ])
    ->beforeUpdateRecord(fn ($record) => [
        'updated_by' => auth()->id(),
    ])
    ->heading('Task Assignments')
    ->defaultRecordDefaults([
        'status' => 'new'
    ])
    ->disableEdit(false)
    ->disableDelete(false);
```

---

## ⚙ API Reference

| Method                   | Description |
|--------------------------|-------------|
| `relationship()`         | Set the Eloquent relationship name |
| `tableColumns()`         | Define table columns (Filament `Columns`) |
| `createFormSchema()`     | Define modal form schema for create/edit |
| `defaultRecordDefaults()`| Default values merged with form submission |
| `beforeCreateRecord()`   | Hook for modifying data before creating a record |
| `beforeUpdateRecord()`   | Hook for modifying data before updating a record |
| `disableCreate()`        | Disable the Create action |
| `disableEdit()`          | Disable the Edit action |
| `disableDelete()`        | Disable the Delete action |
| `heading()`              | Set table heading title |

Hooks receive:
- `data`: submitted form data for that row
- `parentState`: full parent form state
- `state`: current field state
- `record`: the parent model being edited
- `field`: the TableField instance

---

## 📚 License

MIT © Momen Noor

