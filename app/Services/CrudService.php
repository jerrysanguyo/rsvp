<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CrudService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly PlainTextSanitizer $sanitizer,
    ) {}

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
        $attemptedFields = array_keys($attributes);
        $preparedAttributes = null;

        try {
            $preparedAttributes = $this->prepareAttributes($record, $attributes);

            return DB::transaction(function () use ($request, $record, $preparedAttributes, $auditDescription): Model {
                $record->fill($preparedAttributes);
                $record->saveOrFail();

                $this->auditLog->created($request, $record, $auditDescription, mustPersist: true);

                return $record->refresh();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->auditLog->mutationFailed(
                $request,
                'create',
                $record::class,
                $exception,
                attemptedChanges: $preparedAttributes,
                attemptedFields: $attemptedFields,
            );

            throw $exception;
        }
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
        $attemptedFields = array_keys($attributes);
        $preparedAttributes = null;
        $before = $record->attributesToArray();

        try {
            $preparedAttributes = $this->prepareAttributes($record, $attributes);

            return DB::transaction(function () use ($request, $record, $preparedAttributes, $auditDescription): Model {
                $lockedRecord = $this->lockRecord($record);
                $lockedBefore = $lockedRecord->attributesToArray();

                $lockedRecord->fill($preparedAttributes);

                if ($lockedRecord->isDirty()) {
                    $lockedRecord->saveOrFail();
                }

                $this->auditLog->updated(
                    $request,
                    $lockedRecord,
                    $lockedBefore,
                    $auditDescription,
                    mustPersist: true,
                );

                return $lockedRecord->refresh();
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->auditLog->mutationFailed(
                $request,
                'update',
                $record::class,
                $exception,
                $record,
                $before,
                $preparedAttributes,
                $attemptedFields,
            );

            throw $exception;
        }
    }

    /**
     * Delete a locked record and retain its last safe snapshot in the audit log.
     */
    public function destroy(Request $request, Model $record, string $auditDescription): void
    {
        $before = $record->attributesToArray();

        try {
            DB::transaction(function () use ($request, $record, $auditDescription): void {
                $lockedRecord = $this->lockRecord($record);
                $lockedBefore = $lockedRecord->attributesToArray();

                if ($lockedRecord->delete() === false) {
                    throw new RuntimeException('The record could not be deleted.');
                }

                $this->auditLog->deleted(
                    $request,
                    $lockedRecord,
                    $auditDescription,
                    $lockedBefore,
                    mustPersist: true,
                );
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->auditLog->mutationFailed(
                $request,
                'delete',
                $record::class,
                $exception,
                $record,
                $before,
            );

            throw $exception;
        }
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

        return $this->sanitizer->sanitizeArray($attributes);
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
}
