<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class CrudService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Persist a new model using validated, explicitly fillable attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function store(
        Request $request,
        Model $record,
        array $attributes,
        string $auditDescription,
    ): Model {
        $attributes = $this->prepareAttributes($record, $attributes);

        return DB::transaction(function () use ($request, $record, $attributes, $auditDescription): Model {
            $record->fill($attributes);
            $record->saveOrFail();

            $this->auditLog->created($request, $record, $auditDescription, mustPersist: true);

            return $record->refresh();
        }, attempts: 3);
    }

    /**
     * Update a locked record and atomically persist its audit snapshots.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Request $request,
        Model $record,
        array $attributes,
        string $auditDescription,
    ): Model {
        $attributes = $this->prepareAttributes($record, $attributes);

        return DB::transaction(function () use ($request, $record, $attributes, $auditDescription): Model {
            $lockedRecord = $this->lockRecord($record);
            $before = $lockedRecord->attributesToArray();

            $lockedRecord->fill($attributes);

            if ($lockedRecord->isDirty()) {
                $lockedRecord->saveOrFail();
            }

            $this->auditLog->updated(
                $request,
                $lockedRecord,
                $before,
                $auditDescription,
                mustPersist: true,
            );

            return $lockedRecord->refresh();
        }, attempts: 3);
    }

    /**
     * Delete a locked record and retain its last safe snapshot in the audit log.
     */
    public function destroy(Request $request, Model $record, string $auditDescription): void
    {
        DB::transaction(function () use ($request, $record, $auditDescription): void {
            $lockedRecord = $this->lockRecord($record);
            $before = $lockedRecord->attributesToArray();

            if ($lockedRecord->delete() === false) {
                throw new RuntimeException('The record could not be deleted.');
            }

            $this->auditLog->deleted(
                $request,
                $lockedRecord,
                $auditDescription,
                $before,
                mustPersist: true,
            );
        }, attempts: 3);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function prepareAttributes(Model $record, array $attributes): array
    {
        $fillable = $record->getFillable();
        $unexpected = array_diff(array_keys($attributes), $fillable);

        if ($fillable === [] || $unexpected !== []) {
            throw new InvalidArgumentException(sprintf(
                '%s received attributes that are not explicitly fillable: %s',
                $record::class,
                implode(', ', $unexpected ?: array_keys($attributes)),
            ));
        }

        return $this->sanitize($attributes);
    }

    private function lockRecord(Model $record): Model
    {
        if (! $record->exists || $record->getKey() === null) {
            throw new InvalidArgumentException('Only persisted records can be updated or deleted.');
        }

        return $record->newQuery()
            ->whereKey($record->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            if (! mb_check_encoding($value, 'UTF-8')) {
                throw new InvalidArgumentException(sprintf('%s must contain valid UTF-8 text.', $key));
            }

            $originalValue = $value;
            $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value) ?? '';
            $value = strip_tags($value);
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
            $value = str_replace(["\r\n", "\r"], "\n", $value);

            if (class_exists(\Normalizer::class)) {
                $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
            }

            $value = trim($value);

            if ($value === '' && trim($originalValue) !== '') {
                throw ValidationException::withMessages([
                    $key => 'This field must contain valid plain text.',
                ]);
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
