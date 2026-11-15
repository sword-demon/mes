import { Empty, Tabs } from 'antd';
import ResourcePage from '@/pages/basic-data/resource-page';
import {
    createInspectionItem, listInspectionItems, updateInspectionItem,
    createDefectCode, listDefectCodes, updateDefectCode,
} from '@/api/quality';
import type { InspectionItem, InspectionItemPayload, DefectCode, DefectCodePayload } from '@/api/quality';
import { useAuthStore } from '@/stores/auth';
import { can } from '@/utils/permission';

export default function QualityStandards() {
    const user = useAuthStore((state) => state.user);
    const items = [
        ...(can(user, 'quality.standards.index') ? [{
            key: 'standards', label: '检验标准', children: (
                <ResourcePage<InspectionItem, InspectionItemPayload>
                    title="检验项目" resource="quality.standards"
                    list={listInspectionItems} create={createInspectionItem} update={updateInspectionItem}
                    columns={[
                        { title: '编码', dataIndex: 'code' },
                        { title: '名称', dataIndex: 'name' },
                        { title: '标准值', dataIndex: 'standard_value' },
                        { title: '最小值', dataIndex: 'min_value' },
                        { title: '最大值', dataIndex: 'max_value' },
                        { title: '单位', dataIndex: 'unit' },
                    ]}
                    fields={[
                        { name: 'code', label: '编码', required: true },
                        { name: 'name', label: '名称', required: true },
                        { name: 'unit', label: '单位', required: true },
                        { name: 'standard_value', label: '标准值', type: 'number', min: -999999999.999, required: true },
                        { name: 'min_value', label: '最小值', type: 'number', min: -999999999.999, required: true },
                        { name: 'max_value', label: '最大值', type: 'number', min: -999999999.999, required: true },
                        { name: 'status', label: '启用', type: 'switch' },
                    ]}
                />
            ),
        }] : []),
        ...(can(user, 'quality.defect-codes.index') ? [{
            key: 'defects', label: '缺陷代码', children: (
                <ResourcePage<DefectCode, DefectCodePayload>
                    title="缺陷代码" resource="quality.defect-codes"
                    list={listDefectCodes} create={createDefectCode} update={updateDefectCode}
                    columns={[
                        { title: '编码', dataIndex: 'code' },
                        { title: '名称', dataIndex: 'name' },
                        { title: '分类', dataIndex: 'category' },
                    ]}
                    fields={[
                        { name: 'code', label: '编码', required: true },
                        { name: 'name', label: '名称', required: true },
                        { name: 'category', label: '分类', required: true },
                        { name: 'status', label: '启用', type: 'switch' },
                    ]}
                />
            ),
        }] : []),
    ];

    if (items.length === 0) {
        return <Empty description="暂无检验标准查看权限" />;
    }

    return <Tabs items={items} />;
}
