import { useQuery } from '@tanstack/react-query';
import { createTeam, deleteTeam, listTeams, listWorkshops, updateTeam } from '@/api/basic-data';
import type { Team, TeamPayload } from '@/api/basic-data';
import ResourcePage from '../resource-page';

export default function Teams() {
    const workshops = useQuery({
        queryKey: ['basic.workshops', 'options'],
        queryFn: () => listWorkshops({ status: true, per_page: 100 }),
    });

    const options = (workshops.data?.items ?? []).map((workshop) => ({
        label: `${workshop.code} ${workshop.name}`,
        value: workshop.id,
    }));

    return (
        <ResourcePage<Team, TeamPayload>
            title="班组"
            resource="basic.teams"
            list={listTeams}
            create={createTeam}
            update={updateTeam}
            remove={deleteTeam}
            filters={[{ name: 'workshop_id', label: '所属车间', type: 'select', options, loading: workshops.isLoading }]}
            columns={[
                { title: '编码', dataIndex: 'code', width: 140 },
                { title: '名称', dataIndex: 'name' },
                { title: '所属车间', dataIndex: 'workshop', render: (workshop?: Team['workshop']) => workshop?.name },
                { title: '班组长', dataIndex: 'leader', width: 120 },
            ]}
            fields={[
                { name: 'workshop_id', label: '所属车间', type: 'select', required: true, options, loading: workshops.isLoading },
                { name: 'code', label: '编码', required: true },
                { name: 'name', label: '名称', required: true },
                { name: 'leader', label: '班组长' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
