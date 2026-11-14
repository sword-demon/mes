import { useQuery } from '@tanstack/react-query';
import { createEmployee, listEmployees, listTeams, updateEmployee } from '@/api/basic-data';
import type { Employee, EmployeePayload } from '@/api/basic-data';
import { listDepartments } from '@/api/system';
import { useAuthStore } from '@/stores/auth';
import type { DepartmentNode } from '@/types/api';
import { can } from '@/utils/permission';
import ResourcePage from '../resource-page';

function flatten(nodes: DepartmentNode[], depth = 0): { label: string; value: number }[] {
    return nodes.flatMap((node) => [
        { label: `${'　'.repeat(depth)}${node.name}`, value: node.id },
        ...flatten(node.children, depth + 1),
    ]);
}

export default function Employees() {
    const user = useAuthStore((state) => state.user);
    const departments = useQuery({
        queryKey: ['system.departments', 'options'],
        queryFn: listDepartments,
        // The department field belongs to another module's permission; a basic
        // data manager without it must still be able to edit employees.
        enabled: can(user, 'system.departments.index'),
    });
    const teams = useQuery({
        queryKey: ['basic.teams', 'options'],
        queryFn: () => listTeams({ status: true, per_page: 100 }),
    });

    const departmentOptions = flatten(departments.data ?? []);
    const canPickDepartment = can(user, 'system.departments.index');
    const teamOptions = (teams.data?.items ?? []).map((team) => ({
        label: `${team.code} ${team.name}`,
        value: team.id,
    }));

    return (
        <ResourcePage<Employee, EmployeePayload>
            title="员工"
            resource="basic.employees"
            list={listEmployees}
            create={createEmployee}
            update={updateEmployee}
            filters={[{ name: 'team_id', label: '所属班组', type: 'select', options: teamOptions, loading: teams.isLoading }]}
            columns={[
                { title: '工号', dataIndex: 'employee_no', width: 130 },
                { title: '姓名', dataIndex: 'name', width: 110 },
                { title: '所属部门', dataIndex: 'department', render: (department?: Employee['department']) => department?.name },
                { title: '所属班组', dataIndex: 'team', render: (team?: Employee['team']) => team?.name },
                { title: '岗位', dataIndex: 'position', width: 120 },
                { title: '技能等级', dataIndex: 'skill_level', width: 110 },
            ]}
            fields={[
                { name: 'employee_no', label: '工号', required: true },
                { name: 'name', label: '姓名', required: true },
                ...(canPickDepartment
                    ? [{
                          name: 'department_id',
                          label: '所属部门',
                          type: 'select' as const,
                          options: departmentOptions,
                          loading: departments.isLoading,
                      }]
                    : []),
                { name: 'team_id', label: '所属班组', type: 'select', options: teamOptions, loading: teams.isLoading },
                { name: 'position', label: '岗位' },
                { name: 'skill_level', label: '技能等级' },
                { name: 'phone', label: '联系电话' },
                { name: 'status', label: '启用', type: 'switch' },
            ]}
        />
    );
}
