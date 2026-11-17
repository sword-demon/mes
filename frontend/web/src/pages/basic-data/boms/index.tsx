import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { App, Button, Drawer, Form, Input, InputNumber, Modal, Popconfirm, Select, Space, Switch, Table, Tag, Typography } from 'antd';
import { useState } from 'react';
import { listProducts, listProductVersions } from '@/api/products';
import { listMaterials } from '@/api/materials';
import {
    approveBom, createBom, createBomItem, deleteBomItem, listBomItems, listBoms,
    submitBom, updateBom, updateBomItem,
} from '@/api/basic-data';
import type { BomItemPayload, BomPayload } from '@/api/basic-data';
import Can from '@/components/can';
import type { Bom, BomItem } from '@/types/api';
import { ApiRequestError } from '@/utils/api-error';

type Editing = { mode: 'create' } | { mode: 'edit'; bom: Bom } | null;
type ItemEditing = { mode: 'create' } | { mode: 'edit'; item: BomItem } | null;
const AUDIT: Record<Bom['audit_status'], { label: string; color: string }> = {
    draft: { label: '草稿', color: 'default' }, pending: { label: '待审核', color: 'gold' }, approved: { label: '已通过', color: 'green' },
};

export default function Boms() {
    const { message } = App.useApp();
    const client = useQueryClient();
    const [page, setPage] = useState(1);
    const [keyword, setKeyword] = useState('');
    const [editing, setEditing] = useState<Editing>(null);
    const [selected, setSelected] = useState<Bom | null>(null);
    const [itemEditing, setItemEditing] = useState<ItemEditing>(null);
    const [productId, setProductId] = useState<number>();
    const [itemPage, setItemPage] = useState(1);
    const [form] = Form.useForm<BomPayload>();
    const [itemForm] = Form.useForm<BomItemPayload>();
    const routes = useQuery({ queryKey: ['basic.boms', page, keyword], queryFn: () => listBoms({ page, keyword: keyword || undefined }) });
    const products = useQuery({ queryKey: ['basic.products', 'options'], queryFn: () => listProducts({ status: true, per_page: 100 }), enabled: editing?.mode === 'create' });
    const versions = useQuery({
        queryKey: ['basic.boms', 'versions', productId],
        queryFn: () => listProductVersions(productId!),
        enabled: editing?.mode === 'create' && productId !== undefined,
    });
    const materials = useQuery({ queryKey: ['basic.materials', 'options'], queryFn: () => listMaterials({ status: true, per_page: 100 }), enabled: itemEditing !== null });
    const items = useQuery({ queryKey: ['basic.boms', selected?.id, 'items', itemPage], queryFn: () => listBomItems(selected!.id, itemPage), enabled: selected !== null });
    const showError = (error: unknown) => message.error(error instanceof ApiRequestError ? error.message : '操作失败');
    const refresh = () => client.invalidateQueries({ queryKey: ['basic.boms'] });
    const save = useMutation({
        mutationFn: (values: BomPayload) => editing?.mode === 'create' ? createBom(values) : updateBom(editing!.bom.id, values),
        onSuccess: () => { void refresh(); message.success('BOM 已保存'); setEditing(null); }, onError: showError,
    });
    const transition = useMutation({
        mutationFn: ({ bom, action }: { bom: Bom; action: 'submit' | 'approve' }) => action === 'submit' ? submitBom(bom.id) : approveBom(bom.id),
        onSuccess: () => { void refresh(); message.success('审核状态已更新'); }, onError: showError,
    });
    const toggle = useMutation({
        mutationFn: (bom: Bom) => updateBom(bom.id, { status: !bom.status }),
        onSuccess: () => { void refresh(); message.success('状态已更新'); }, onError: showError,
    });
    const saveItem = useMutation({
        mutationFn: (values: BomItemPayload) => itemEditing?.mode === 'create' ? createBomItem(selected!.id, values) : updateBomItem(selected!.id, itemEditing!.item.id, values),
        onSuccess: () => { void client.invalidateQueries({ queryKey: ['basic.boms', selected?.id, 'items'] }); message.success('子项已保存'); setItemEditing(null); }, onError: showError,
    });
    const removeItem = useMutation({
        mutationFn: (item: BomItem) => deleteBomItem(selected!.id, item.id),
        onSuccess: () => { void client.invalidateQueries({ queryKey: ['basic.boms', selected?.id, 'items'] }); message.success('子项已删除'); }, onError: showError,
    });

    return <>
        <Space className="mb-4 w-full justify-between" wrap>
            <Typography.Title level={4} className="mb-0!">BOM 管理</Typography.Title>
            <Space><Input.Search allowClear className="w-56" placeholder="BOM 编码 / 版本" onSearch={(value) => { setKeyword(value); setPage(1); }} />
                <Can code="button.basic.boms.create"><Button type="primary" onClick={() => { setProductId(undefined); form.resetFields(); setEditing({ mode: 'create' }); }}>新增 BOM</Button></Can>
            </Space>
        </Space>
        <Table<Bom> rowKey="id" loading={routes.isLoading} dataSource={routes.data?.items} pagination={{ current: page, pageSize: routes.data?.meta.per_page, total: routes.data?.meta.total, onChange: setPage }} columns={[
            { title: 'BOM 编码', dataIndex: 'code' },
            { title: '产品', key: 'product', render: (_, bom) => `${bom.product_version?.product.code ?? '-'} ${bom.product_version?.product.name ?? ''}` },
            { title: '产品版本', key: 'product_version', render: (_, bom) => bom.product_version?.version ?? '-' },
            { title: 'BOM 版本', dataIndex: 'version' },
            { title: '审核状态', dataIndex: 'audit_status', render: (value: Bom['audit_status']) => <Tag color={AUDIT[value]?.color}>{AUDIT[value]?.label ?? value}</Tag> },
            { title: '状态', dataIndex: 'status', render: (value: boolean) => value ? '启用' : '停用' },
            { title: '操作', key: 'actions', render: (_, bom) => <Space wrap>
                <Button type="link" onClick={() => { setItemPage(1); setSelected(bom); }}>子项</Button>
                <Can code="button.basic.boms.update"><Button type="link" disabled={toggle.isPending} onClick={() => toggle.mutate(bom)}>{bom.status ? '停用' : '启用'}</Button></Can>
                {bom.audit_status === 'draft' && <>
                    <Can code="button.basic.boms.update"><Button type="link" onClick={() => { form.setFieldsValue({ code: bom.code, product_version_id: bom.product_version_id, version: bom.version, remark: bom.remark, status: bom.status }); setEditing({ mode: 'edit', bom }); }}>编辑</Button></Can>
                    <Can code="button.basic.boms.submit"><Popconfirm title="确认提交审核？" onConfirm={() => transition.mutate({ bom, action: 'submit' })}><Button type="link">提交审核</Button></Popconfirm></Can>
                </>}
                {bom.audit_status === 'pending' && <Can code="button.basic.boms.audit"><Popconfirm title="确认审核通过？" onConfirm={() => transition.mutate({ bom, action: 'approve' })}><Button type="link">审核通过</Button></Popconfirm></Can>}
            </Space> },
        ]} />

        <Modal open={editing !== null} title={editing?.mode === 'create' ? '新增 BOM' : '编辑 BOM'} confirmLoading={save.isPending} onCancel={() => setEditing(null)} onOk={() => form.submit()}>
            <Form key={editing?.mode === 'create' ? 'create' : editing?.bom.id} form={form} layout="vertical" initialValues={editing?.mode === 'edit' ? { code: editing.bom.code, product_version_id: editing.bom.product_version_id, version: editing.bom.version, remark: editing.bom.remark, status: editing.bom.status } : { status: true }} onFinish={(values) => save.mutate(values)}>
                <Form.Item name="code" label="BOM 编码" rules={[{ required: true }]}><Input /></Form.Item>
                {editing?.mode === 'create' ? <>
                    <Form.Item label="产品" required><Select value={productId} showSearch optionFilterProp="label" loading={products.isLoading} onChange={(id: number) => { setProductId(id); form.setFieldValue('product_version_id', undefined); }} options={(products.data?.items ?? []).map((product) => ({ value: product.id, label: `${product.code} ${product.name}` }))} /></Form.Item>
                    <Form.Item name="product_version_id" label="产品版本" rules={[{ required: true }]}>
                        <Select loading={versions.isFetching} disabled={productId === undefined} options={(versions.data?.items ?? []).filter((version) => version.status).map((version) => ({ value: version.id, label: version.version }))} placeholder="选择产品版本" />
                    </Form.Item>
                </> : <Form.Item label="产品版本">{editing?.bom.product_version?.product.name} · {editing?.bom.product_version?.version}</Form.Item>}
                <Form.Item name="version" label="BOM 版本" rules={[{ required: true }]}><Input placeholder="V1.0" /></Form.Item>
                <Form.Item name="remark" label="备注"><Input.TextArea rows={2} /></Form.Item>
                <Form.Item name="status" label="启用" valuePropName="checked"><Switch /></Form.Item>
            </Form>
        </Modal>

        <Drawer open={selected !== null} width={760} title={selected ? `BOM 子项 · ${selected.code} ${selected.version}` : 'BOM 子项'} onClose={() => setSelected(null)} destroyOnHidden>
            <Space className="mb-3 w-full justify-end"><Can code="button.basic.boms.create"><Button type="primary" disabled={selected?.audit_status !== 'draft'} onClick={() => { itemForm.resetFields(); setItemEditing({ mode: 'create' }); }}>新增子项</Button></Can></Space>
            <Table<BomItem> rowKey="id" size="small" loading={items.isLoading} dataSource={items.data?.items} pagination={{ current: itemPage, pageSize: items.data?.meta.per_page, total: items.data?.meta.total, onChange: setItemPage, showSizeChanger: false }} columns={[
                { title: '物料编码', key: 'code', render: (_, item) => item.material?.code ?? '-' },
                { title: '物料名称', key: 'name', render: (_, item) => item.material?.name ?? '-' },
                { title: '用量', dataIndex: 'quantity' }, { title: '损耗率 %', dataIndex: 'loss_rate' },
                { title: '操作', key: 'actions', render: (_, item) => selected?.audit_status === 'draft' ? <Space>
                    <Can code="button.basic.boms.update"><Button type="link" onClick={() => { itemForm.setFieldsValue({ material_id: item.material_id, quantity: Number(item.quantity), loss_rate: Number(item.loss_rate) }); setItemEditing({ mode: 'edit', item }); }}>编辑</Button></Can>
                    <Can code="button.basic.boms.update"><Popconfirm title="删除该子项？" onConfirm={() => removeItem.mutate(item)}><Button type="link" danger>删除</Button></Popconfirm></Can>
                </Space> : null },
            ]} />
        </Drawer>
        <Modal open={itemEditing !== null} title={itemEditing?.mode === 'create' ? '新增 BOM 子项' : '编辑 BOM 子项'} confirmLoading={saveItem.isPending} onCancel={() => setItemEditing(null)} onOk={() => itemForm.submit()}>
            <Form key={itemEditing?.mode === 'create' ? 'item-create' : itemEditing?.item.id} form={itemForm} layout="vertical" initialValues={itemEditing?.mode === 'edit' ? { material_id: itemEditing.item.material_id, quantity: Number(itemEditing.item.quantity), loss_rate: Number(itemEditing.item.loss_rate) } : { quantity: 1, loss_rate: 0 }} onFinish={(values) => saveItem.mutate(values)}>
                <Form.Item name="material_id" label="物料" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" loading={materials.isLoading} disabled={itemEditing?.mode === 'edit'} options={(materials.data?.items ?? []).map((material) => ({ value: material.id, label: `${material.code} ${material.name}` }))} /></Form.Item>
                <Form.Item name="quantity" label="用量" rules={[{ required: true, type: 'number', min: 0, message: '用量必须大于 0' }]}><InputNumber min={0.001} precision={3} className="w-full" /></Form.Item>
                <Form.Item name="loss_rate" label="损耗率 %" rules={[{ type: 'number', min: 0, max: 100 }]}><InputNumber min={0} max={100} precision={2} className="w-full" /></Form.Item>
            </Form>
        </Modal>
    </>;
}
