import { createFactory, listFactories, updateFactory } from '@/api/basic-data';
import type { Factory, FactoryPayload } from '@/api/basic-data';
import ResourcePage from '../resource-page';

export default function Factories() {
    return (
        <ResourcePage<Factory, FactoryPayload>
            title="工厂"
            resource="basic.factories"
            list={listFactories}
            create={createFactory}
            update={updateFactory}
            columns={[
                { title: '编码', dataIndex: 'code', width: 140 },
                { title: '名称', dataIndex: 'name' },
                { title: '地址', dataIndex: 'address' },
                { title: '联系人', dataIndex: 'contact', width: 120 },
                { title: '联系电话', dataIndex: 'phone', width: 150 },
            ]}
            fields={[
                { name: 'code', label: '编码', required: true },
                { name: 'name', label: '名称', required: true },
                { name: 'address', label: '地址' },
                { name: 'contact', label: '联系人' },
                { name: 'phone', label: '联系电话' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
