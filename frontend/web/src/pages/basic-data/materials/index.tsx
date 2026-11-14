import { Tag } from 'antd';
import {
    createMaterial,
    listMaterials,
    MATERIAL_TYPES,
    updateMaterial,
} from '@/api/materials';
import type { Material, MaterialPayload } from '@/api/materials';
import ResourcePage from '../resource-page';

const typeOptions = MATERIAL_TYPES.map((type) => ({ label: type, value: type }));

export default function Materials() {
    return (
        <ResourcePage<Material, MaterialPayload>
            title="物料"
            resource="basic.materials"
            list={listMaterials}
            create={createMaterial}
            update={updateMaterial}
            filters={[
                {
                    name: 'type',
                    label: '类型',
                    type: 'select',
                    options: typeOptions,
                },
            ]}
            columns={[
                { title: '编码', dataIndex: 'code', width: 120 },
                { title: '名称', dataIndex: 'name' },
                { title: '类型', dataIndex: 'type', width: 110 },
                { title: '规格', dataIndex: 'specification' },
                { title: '单位', dataIndex: 'unit', width: 80 },
                { title: '最低库存', dataIndex: 'min_stock', width: 110 },
                { title: '安全库存', dataIndex: 'safety_stock', width: 110 },
                {
                    title: '批次管理',
                    dataIndex: 'batch_managed',
                    width: 110,
                    render: (managed: boolean) => (
                        <Tag color={managed ? 'blue' : 'default'}>{managed ? '批次' : '不批次'}</Tag>
                    ),
                },
            ]}
            fields={[
                { name: 'code', label: '编码', required: true },
                { name: 'name', label: '名称', required: true },
                {
                    name: 'type',
                    label: '类型',
                    type: 'select',
                    required: true,
                    options: typeOptions,
                },
                { name: 'specification', label: '规格' },
                { name: 'unit', label: '单位', required: true },
                { name: 'min_stock', label: '最低库存', type: 'number' },
                { name: 'safety_stock', label: '安全库存', type: 'number' },
                { name: 'batch_managed', label: '批次管理', type: 'switch' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
