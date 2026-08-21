<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditLogService
{
    /** @var list<string> */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'passwd',
        'remember_token',
        'token',
        'secret',
        'authorization',
        'cookie',
        'session_id',
        'api_key',
        'private_key',
        'submission_key',
        '_token',
    ];

    public function created(Request $request, Model $record, string $description, bool $mustPersist = false): void
    {
        $this->write(
            request: $request,
            logName: 'records',
            event: 'record.created',
            description: $description,
            subject: $record,
            properties: ['before' => null, 'after' => $this->safeAttributes($record)],
            mustPersist: $mustPersist,
        );
    }

    /** @param array<string, mixed> $before */
    public function updated(
        Request $request,
        Model $record,
        array $before,
        string $description,
        bool $mustPersist = false,
    ): void {
        $safeBefore = $this->redact($before);
        $safeAfter = $this->safeAttributes($record);

        $this->write(
            request: $request,
            logName: 'records',
            event: 'record.updated',
            description: $description,
            subject: $record,
            properties: [
                'before' => $safeBefore,
                'after' => $safeAfter,
                'changed_fields' => $this->changedFields($safeBefore, $safeAfter),
            ],
            mustPersist: $mustPersist,
        );
    }

    /** @param array<string, mixed>|null $before */
    public function deleted(
        Request $request,
        Model $record,
        string $description,
        ?array $before = null,
        bool $mustPersist = false,
    ): void {
        $this->write(
            request: $request,
            logName: 'records',
            event: 'record.deleted',
            description: $description,
            subject: $record,
            properties: ['before' => $this->redact($before ?? $record->attributesToArray()), 'after' => null],
            mustPersist: $mustPersist,
        );
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $attemptedChanges
     * @param  list<string>  $attemptedFields
     * @param  list<string>  $validationFields
     */
    public function mutationFailed(
        Request $request,
        string $action,
        string $modelType,
        Throwable $exception,
        ?Model $subject = null,
        ?array $before = null,
        ?array $attemptedChanges = null,
        array $attemptedFields = [],
        array $validationFields = [],
        string $stage = 'persistence',
    ): void {
        $request->attributes->set('mutation_failure_audited', true);

        if ($route = $request->route()) {
            $route->setAction([...$route->getAction(), 'mutation_failure_audited' => true]);
        }

        $this->write(
            request: $request,
            logName: 'records',
            event: "record.{$action}_failed",
            description: sprintf('%s %s failed', class_basename($modelType), $action),
            subject: $subject?->exists ? $subject : null,
            properties: [
                'action' => $action,
                'model_type' => $modelType,
                'target_id' => $subject?->getKey(),
                'before' => $before === null ? null : $this->redact($before),
                'attempted_changes' => $attemptedChanges === null ? null : $this->redact($attemptedChanges),
                'attempted_fields' => $this->safeFieldNames($attemptedFields),
                'validation_fields' => $this->safeFieldNames($validationFields),
                'failure_stage' => $stage,
                'exception' => $exception::class,
            ],
        );
    }

    /** @param array<string, mixed> $properties */
    public function authentication(
        Request $request,
        string $event,
        string $description,
        ?Model $actor = null,
        array $properties = [],
        bool $mustPersist = false,
    ): void {
        $this->write(
            request: $request,
            logName: 'authentication',
            event: $event,
            description: $description,
            actor: $actor,
            properties: $properties,
            mustPersist: $mustPersist,
        );
    }

    public function httpRequest(Request $request, int $statusCode, float $durationMs, ?Throwable $exception = null): void
    {
        $routeName = $request->route()?->getName();
        $failed = $exception !== null || $statusCode >= 400;
        $description = sprintf(
            'HTTP %s %s %s',
            $request->method(),
            $routeName ?: ($request->route()?->uri() ?? $request->path()),
            $failed ? 'failed' : 'completed',
        );

        $properties = [
            'status_code' => $statusCode,
            'duration_ms' => round($durationMs, 2),
            'outcome' => $statusCode >= 400 ? 'failure' : 'success',
        ];

        if ($exception) {
            $properties['exception'] = $exception::class;
        }

        $this->write(
            request: $request,
            logName: 'http',
            event: $failed ? 'http.failed' : 'http.completed',
            description: $description,
            properties: $properties,
        );
    }

    /** @param array<string, mixed> $properties */
    private function write(
        Request $request,
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $actor = null,
        array $properties = [],
        bool $mustPersist = false,
    ): void {
        try {
            $activity = activity($logName)
                ->event($event)
                ->withProperties(array_merge($this->requestContext($request), $this->redact($properties)));

            $actor ??= $request->user();

            if ($actor) {
                $activity->causedBy($actor);
            }

            if ($subject) {
                $activity->performedOn($subject);
            }

            $activity->log($description);
        } catch (Throwable $exception) {
            Log::error('Audit log could not be persisted.', [
                'audit_event' => $event,
                'request_id' => $request->attributes->get('audit_request_id'),
                'exception' => $exception::class,
            ]);

            if ($mustPersist) {
                throw $exception;
            }
        }
    }

    /** @return array<string, mixed> */
    private function requestContext(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get('audit_request_id', (string) Str::uuid()),
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'route_template' => $request->route()?->uri() ?? $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ];
    }

    /** @return array<string, mixed> */
    private function safeAttributes(Model $record): array
    {
        return $this->redact($record->attributesToArray());
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (! array_key_exists($key, $before)
                || ! array_key_exists($key, $after)
                || $before[$key] !== $after[$key]) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /** @param list<string> $fields
     * @return list<string>
     */
    private function safeFieldNames(array $fields): array
    {
        return array_values(array_filter(array_unique($fields), function (string $field): bool {
            $rootField = Str::lower(Str::before($field, '.'));

            return ! $this->isSensitiveKey($rootField);
        }));
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = Str::lower(str_replace(['-', '.'], '_', $key));

        return collect(self::SENSITIVE_KEY_FRAGMENTS)
            ->contains(fn (string $fragment): bool => str_contains($normalizedKey, $fragment));
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                unset($values[$key]);

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
