import type { InspectionTask } from '@/api/quality';

export function operationLabel(task: Pick<InspectionTask, 'route_operation' | 'process_record'>): string {
    if (!task.route_operation) {
        return '—';
    }

    const label = `${task.route_operation.sequence}. ${task.route_operation.process.name}`;

    return task.process_record?.sn ? `${label}（${task.process_record.sn}）` : label;
}
