import { createProcess, listProcesses, updateProcess } from '@/api/basic-data';
import type { ProcessPayload } from '@/api/basic-data';
import type { Process } from '@/types/api';
import ResourcePage from '../resource-page';

const PROCESS_TYPES = ['准备', '加工', '检验', '包装'].map((type) => ({ label: type, value: type }));

export default function Processes() {
    return (
        <ResourcePage<Process, ProcessPayload>
            title="工序管理"
            resource="basic.processes"
            list={listProcesses}
            create={createProcess}
            update={updateProcess}
            filters={[{ name: 'type', label: '工序类型', type: 'select', options: PROCESS_TYPES }]}
            columns={[
                { title: '编码', dataIndex: 'code', width: 140 },
                { title: '名称', dataIndex: 'name' },
                { title: '类型', dataIndex: 'type', width: 100 },
                { title: '标准工时', dataIndex: 'standard_hours', width: 110 },
                { title: '需检验', dataIndex: 'requires_inspection', width: 100, render: (value: boolean) => value ? '是' : '否' },
                { title: '描述', dataIndex: 'description', render: (value: string | null) => value || '-' },
            ]}
            fields={[
                { name: 'code', label: '编码', required: true },
                { name: 'name', label: '名称', required: true },
                { name: 'type', label: '类型', type: 'select', required: true, options: PROCESS_TYPES },
                { name: 'standard_hours', label: '标准工时', type: 'number' },
                { name: 'requires_inspection', label: '需要检验', type: 'switch' },
                { name: 'description', label: '描述' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
