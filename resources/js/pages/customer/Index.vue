<!-- resources/js/pages/customer/Show.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { index, show } from '@/routes/customer';
import { Head, router } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { DataTable, Column, Button, InputText, Tag } from 'primevue';
import { computed, ref } from 'vue';
import { fmtDate, fmtPhone } from '@/lib/utils';

type Customer = {
    id: number;
    legacy_id: number;
    full_name: string;
    first_name: string;
    last_name: string;
    phone_1: string | null;
    phone_2: string | null;
    email_1: string | null;
    email_2: string | null;
    social_media_links: Record<string, string> | null;
    is_realtor: boolean | null;
    latest_inspection: any | null;
    created_at: string;
    updated_at: string;
    last_contact_at: string | null;
    last_contact_type: string | null;
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Customers',
        href: index().url,
    },
];

const props = defineProps<{
    customers: {
        data:           Customer[]
        total:          number
        per_page:       number
        current_page:   number
        from:           number | null
        to:             number | null
    },
    filters: {
        search:         string
        per_page:       number
        sort_field:     string
        sort_order:     number
    }
}>()

// UI state for query params
const search    = ref(props.filters.search);
const perPage   = ref(props.filters.per_page);
const sortField = ref(props.filters.sort_field);
const sortOrder = ref(props.filters.sort_order);

// DataTable helpers
const first     = computed(() => (props.customers.current_page - 1) * props.customers.per_page);

function reload(extra: Record<string, unknown> = {}) {
    router.get(index().url,
        {
            search:     search.value,
            per_page:   perPage.value,
            sort_field: sortField.value,
            sort_order: sortOrder.value,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    )
}

function onPage(e: any) {
    reload({ page: e.page + 1 })
}

function onSort(e: any) {
    sortField.value = e.sortField
    sortOrder.value = e.sortOrder
    reload({ page: 1 })
}

function onSearchEnter() {
    reload({ page: 1 })
}

function onRowClick(customerId: number) {
    router.get(show(customerId));
}



</script>

<template>
    <Head
        title="Customers"
    />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl"
        >
            <!-- Wrapper -->
            <div class="px-4 pt-4 space-y-3">

                <!-- Row A: search + search button -->
                <div class="flex w-full items-start gap-2">
                    <InputText
                        v-model="search"
                        placeholder="Search name, email, phone number..."
                        @keydown.enter="onSearchEnter"
                        class="w-full"
                    />
                    <Button
                        label="Search"
                        icon="pi pi-search"
                        @click="onSearchEnter"
                        class="whitespace-nowrap w-34"
                    />
                </div>

            </div>


            <!-- DataTable -->
            <DataTable
                :value="customers.data"
                data-key="id"
                :lazy="true"
                :total-records="customers.total"
                :paginator="true"
                :rows="customers.per_page"
                :first="first"
                :sort-field="sortField"
                :sort-order="sortOrder"
                @page="onPage"
                @sort="onSort"
                @row-click="(e) => (onRowClick(e.data.id))"
                :rows-per-page-options="[10,25,50,100]"
                responsive-layout="scroll"
                class="rounded-lg hover:cursor-pointer"
                removable-sort
            >
                <Column field="last_name" header="Name" sortable>
                    <template #body="{ data }">
                        {{ data.full_name }}
                    </template>
                </Column>

                <Column field="phone_1" header="Phone" sortable>
                    <template #body="{ data }">
                        {{ fmtPhone(data.phone_1) }}
                    </template>
                </Column>

                <Column field="email_1" header="Email" sortable />

                <Column field="is_realtor" header="Realtor?" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.is_realtor ? 'Yes' : 'No'" :severity="data.is_realtor ? 'success' : 'danger'" />
                    </template>
                </Column>

                <Column field="last_contact_at" header="Last Contacted" sortable>
                    <template #body="{ data }">
                        {{ fmtDate(data.last_contact_at) ?? 'N/A' }}
                    </template>
                </Column>

                <Column field="created_at" header="Created At" sortable>
                    <template #body="{ data }">
                        {{ fmtDate(data.created_at) }}
                    </template>
                </Column>

            </DataTable>


        </div>
    </AppLayout>
</template>
