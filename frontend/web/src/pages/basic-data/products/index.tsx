import { Button } from 'antd';
import { useState } from 'react';
import Can from '@/components/can';
import {
    createProduct,
    deleteProduct,
    listProducts,
    updateProduct,
} from '@/api/products';
import type { Product, ProductPayload } from '@/api/products';
import ResourcePage from '../resource-page';
import ProductVersionsDrawer from './versions-drawer';

const categories = ['成品', '半成品', '部件'];

export default function Products() {
    const [versionProduct, setVersionProduct] = useState<Product | null>(null);

    return (
        <>
            <ResourcePage<Product, ProductPayload>
                title="产品"
                resource="basic.products"
                list={listProducts}
                create={createProduct}
                update={updateProduct}
                remove={deleteProduct}
                filters={[
                    {
                        name: 'category',
                        label: '分类',
                        type: 'select',
                        options: categories.map((category) => ({ label: category, value: category })),
                    },
                ]}
                columns={[
                    { title: '编码', dataIndex: 'code', width: 120 },
                    { title: '名称', dataIndex: 'name' },
                    { title: '型号', dataIndex: 'model', width: 120 },
                    { title: '规格', dataIndex: 'specification' },
                    { title: '分类', dataIndex: 'category', width: 100 },
                    { title: '单位', dataIndex: 'unit', width: 80 },
                ]}
                fields={[
                    { name: 'code', label: '编码', required: true },
                    { name: 'name', label: '名称', required: true },
                    { name: 'model', label: '型号' },
                    { name: 'specification', label: '规格' },
                    {
                        name: 'category',
                        label: '分类',
                        type: 'select',
                        required: true,
                        options: categories.map((category) => ({ label: category, value: category })),
                    },
                    { name: 'unit', label: '单位', required: true },
                    { name: 'status', label: '启用', type: 'switch' },
                ]}
                extraActions={(record) => (
                    <Can code="button.basic.products.update">
                        <Button type="link" onClick={() => setVersionProduct(record)}>
                            版本
                        </Button>
                    </Can>
                )}
            />
            <ProductVersionsDrawer
                product={versionProduct}
                onClose={() => setVersionProduct(null)}
            />
        </>
    );
}
