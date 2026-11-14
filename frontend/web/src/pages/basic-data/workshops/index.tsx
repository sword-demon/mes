import { useQuery } from '@tanstack/react-query';
import { createWorkshop, listFactories, listWorkshops, updateWorkshop } from '@/api/basic-data';
import type { Workshop, WorkshopPayload } from '@/api/basic-data';
import ResourcePage from '../resource-page';

export default function Workshops() {
    // Only enabled factories may own new workshops.
    const factories = useQuery({
        queryKey: ['basic.factories', 'options'],
        queryFn: () => listFactories({ status: true, per_page: 100 }),
    });

    const options = (factories.data?.items ?? []).map((factory) => ({
        label: `${factory.code} ${factory.name}`,
        value: factory.id,
    }));

    return (
        <ResourcePage<Workshop, WorkshopPayload>
            title="车间"
            resource="basic.workshops"
            list={listWorkshops}
            create={createWorkshop}
            update={updateWorkshop}
            filters={[{ name: 'factory_id', label: '所属工厂', type: 'select', options, loading: factories.isLoading }]}
            columns={[
                { title: '编码', dataIndex: 'code', width: 140 },
                { title: '名称', dataIndex: 'name' },
                { title: '所属工厂', dataIndex: 'factory', render: (factory?: Workshop['factory']) => factory?.name },
                { title: '负责人', dataIndex: 'manager', width: 120 },
            ]}
            fields={[
                { name: 'factory_id', label: '所属工厂', type: 'select', required: true, options, loading: factories.isLoading },
                { name: 'code', label: '编码', required: true },
                { name: 'name', label: '名称', required: true },
                { name: 'manager', label: '负责人' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
