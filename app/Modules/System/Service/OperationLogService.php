<?php

namespace App\Modules\System\Service;

use App\Modules\System\Model\OperationLog;
use App\Modules\System\Model\User;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OperationLogService
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record one key write with its actor, target and before/after snapshot.
     *
     * Snapshot keys are human-readable labels; the service keeps only the
     * fields whose values changed and builds the summary from them.
     */
    public function record(OperationLogEntry $entry, ?User $actor = null, ?string $ip = null): OperationLog
    {
        $actor ??= $this->request->user();
        $actor = $actor instanceof User ? $actor : null;
        $changes = $this->changes($entry->before, $entry->after);
        $objectName = Str::substr($entry->objectName, 0, 255);

        return OperationLog::query()->create([
            'user_id' => $actor?->id,
            'user_name' => $actor === null ? '系统' : Str::substr($actor->name, 0, 64),
            'action' => $entry->action,
            'action_name' => $entry->actionName,
            'object_type' => $entry->objectType,
            'object_id' => $entry->objectId,
            'object_name' => $objectName,
            'summary' => $this->summary($entry->actionName, $entry->objectType, $objectName, $changes),
            'changes' => $changes === [] ? null : $changes,
            'ip' => $ip ?? (string) $this->request->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (array_keys($before + $after) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($old === $new) {
                continue;
            }

            $changes[$field] = ['before' => $old, 'after' => $new];
        }

        return $changes;
    }

    /**
     * @param  array<string, array{before: mixed, after: mixed}>  $changes
     */
    private function summary(string $actionName, string $objectType, string $objectName, array $changes): string
    {
        $detail = collect($changes)
            ->map(fn (array $change, string $field): string => $field.'：'.$this->display($change['before']).' → '.$this->display($change['after']))
            ->implode('；');

        $summary = $actionName.'：'.$objectType.' '.$objectName;

        if ($detail !== '') {
            $summary .= '（'.$detail.'）';
        }

        return Str::limit($summary, 480, '…');
    }

    private function display(mixed $value): string
    {
        return match (true) {
            $value === null => '空',
            is_bool($value) => $value ? '是' : '否',
            is_array($value) => $value === [] ? '空' : implode('、', array_map(fn (mixed $item): string => $this->display($item), $value)),
            default => (string) $value,
        };
    }
}
