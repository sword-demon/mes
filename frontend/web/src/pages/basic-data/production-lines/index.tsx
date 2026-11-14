import { useQuery } from '@tanstack/react-query';
import { createProductionLine, listProductionLines, listWorkshops, updateProductionLine } from '@/api/basic-data';
import type { ProductionLine, ProductionLinePayload } from '@/api/basic-data';
import ResourcePage from '../resource-page';

export default function ProductionLines() {
    const workshops = useQuery({
        queryKey: ['basic.workshops', 'options'],
        queryFn: () => listWorkshops({ status: true, per_page: 100 }),
    });

    const options = (workshops.data?.items ?? []).map((workshop) => ({
        label: `${workshop.code} ${workshop.name}`,
        value: workshop.id,
    }));

    return (
        <ResourcePage<ProductionLine, ProductionLinePayload>
            title="产线"
            resource="basic.lines"
            list={listProductionLines}
            create={createProductionLine}
            update={updateProductionLine}
            filters={[{ name: 'workshop_id', label: '所属车间', type: 'select', options, loading: workshops.isLoading }]}
            columns={[
                { title: '编码', dataIndex: 'code', width: 140 },
                { title: '名称', dataIndex: 'name' },
                { title: '所属车间', dataIndex: 'workshop', render: (workshop?: ProductionLine['workshop']) => workshop?.name },
                { title: '生产能力', dataIndex: 'capacity', width: 110 },
                { title: '负责人', dataIndex: 'manager', width: 120 },
            ]}
            fields={[
                { name: 'workshop_id', label: '所属车间', type: 'select', required: true, options, loading: workshops.isLoading },
                { name: 'code', label: '编码', required: true },
                { name: 'name', label: '名称', required: true },
                { name: 'capacity', label: '生产能力', type: 'number' },
                { name: 'manager', label: '负责人' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
