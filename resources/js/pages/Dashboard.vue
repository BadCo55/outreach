<!-- resources/js/pages/Dashboard.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Column, DataTable, Select, InputText, Checkbox, Button } from 'primevue';
import { computed, ref, watch } from 'vue';


type Row = {
    customer: {
        first_name: string | null;
        last_name: string | null;
        phone_1: string | null;
        phone_2: string | null;
        email_1: string | null;
        email_2: string | null;
        is_realtor: boolean | null;
    };
    inspection: {
        id: number | null;
        date: string | null;
        date_raw: string | null;
        fee: string | null;
        months_since: number | null;
        customer_role: string | null;
    };
    property: {
        street_address: string | null;
        city: string | null;
        state: string | null;
        property_type: string | null;
        square_footage: string | null;
    };
};
type Meta = {
    total: number;
    page: number;
    perPage: number;
    lastPage: number;
    from: number;
    to: number;
    source?: string;
    olderMonths?: number;
    search?: string;
    sortBy?: string;
    sortDir?: string;
};

const page = usePage<{ rows: Row[]; meta: Meta }>();
const rows = computed(() => page.props.rows ?? []);
const meta = computed(
    () =>
        page.props.meta ?? {
            total: 0,
            page: 1,
            perPage: 25,
            lastPage: 1,
            from: 0,
            to: 0,
        },
);

// Local pagination (so we don’t need to double-click)
const first = ref(0);
const rowsPerPage = ref(25);
watch(
    meta,
    (m) => {
        rowsPerPage.value = m.perPage;
        first.value = (m.page - 1) * m.perPage;
    },
    { immediate: true },
);

// Filter/sort model (bound to URL)
const olderMonths = ref<number>(meta.value.olderMonths ?? 0);
const search = ref<string>(meta.value.search ?? '');
const sortBy = ref<string>(meta.value.sortBy ?? 'date_raw');
const sortDir = ref<string>(meta.value.sortDir ?? 'desc');
const showRealtorsOnly = ref(false)

// Trigger a reload with current query (keep pagination at 1 on filter changes)
function reload(pageNum?: number) {
    router.get(
        '/dashboard',
        {
            page: pageNum ?? Math.floor(first.value / rowsPerPage.value) + 1,
            perPage: rowsPerPage.value,
            olderMonths: olderMonths.value || undefined,
            search: search.value || undefined,
            sortBy: sortBy.value,
            sortDir: sortDir.value,
            realtorOnly: showRealtorsOnly.value ? 1 : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

// Pagination event
function onPage(e: { first: number; rows: number; page: number }) {
    first.value = e.first;
    rowsPerPage.value = e.rows;
    reload();
}

// Filter changes → go back to page 1
function onFiltersChanged() {
    first.value = 0;
    reload(1);
}

function onRowClick(e: { data: Row }) {
  const id = e.data?.customer?.id as unknown as number | undefined
  if (!id) return
  // If you have Ziggy's route() helper:
  // router.get(route('customers.intake.start', { legacyId: id }))
  // Or simply:
  router.get(`/customer/intake/${id}`)
}

const olderThanOptions = [
    { label: 'Any Time', value: 0 },
    { label: '3 months', value: 3 },
    { label: '6 months', value: 6 },
    { label: '12 months', value: 12 },
    { label: '18 months', value: 18 },
    { label: '24 months', value: 24 },
    { label: '36 months', value: 36 },
]

const sortByOptions = [
    { label: 'Last Inspection', value: 'date_raw' },
    { label: 'Last Name', value: 'last_name' },
    { label: 'Fee', value: 'fee' },
]

</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <!-- Controls -->
        <div class="mb-3 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-6 items-end">
                <!-- Older than -->
                <div>
                    <label for="older_than" class="block text-xs">Older than</label>
                    <Select
                        id="older_than"
                        fluid
                        v-model.number="olderMonths"
                        @change="onFiltersChanged"
                        :options="olderThanOptions"
                        option-label="label"
                        option-value="value"
                    />
                </div>

                <!-- Search: spans 2 cols on lg for breathing room -->
                <div class="md:col-span-2 lg:col-span-2">
                    <label for="search" class="block text-xs">Search</label>
                    <InputText
                        id="search"
                        fluid
                        v-model.trim="search"
                        @keyup.enter="onFiltersChanged"
                        placeholder="Search name, email, phone, address..."
                    />
                </div>

                <!-- Sort by -->
                <div>
                    <label for="sort_by" class="block text-xs">Sort by</label>
                    <Select
                        id="sort_by"
                        fluid
                        v-model="sortBy"
                        @change="onFiltersChanged"
                        :options="sortByOptions"
                        option-label="label"
                        option-value="value"
                    />
                </div>

                <!-- Direction -->
                <div>
                    <label for="sort_dir" class="block text-xs">Sort Direction</label>
                    <Select
                        id="sort_dir"
                        fluid
                        v-model="sortDir"
                        @change="onFiltersChanged"
                        :options="[
                        { label: 'Ascending', value: 'asc' },
                        { label: 'Descending', value: 'desc' },
                        ]"
                        option-label="label"
                        option-value="value"
                    />
                </div>

                <!-- Right rail (lg+): Realtors only + Apply -->
                <div class="md:col-span-2 lg:col-span-1">
                    <div class="flex flex-col gap-2 lg:items-end">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                binary
                                v-model="showRealtorsOnly"
                                input-id="show_realtors_only"
                            />
                            <label for="show_realtors_only" class="text-sm">Realtors Only</label>
                        </div>

                        <Button
                            fluid
                            label="Apply"
                            class="w-full lg:w-auto"
                            @click="onFiltersChanged"
                        />
                    </div>
                </div>
            </div>
        </div>

        <DataTable
            :value="rows"
            :lazy="true"
            paginator
            :first="first"
            :rows="rowsPerPage"
            :totalRecords="meta.total"
            :rowsPerPageOptions="[10, 25, 50, 100]"
            @page="onPage"
            tableStyle="min-width: 70rem"
            @row-click="onRowClick"
            class="hover:cursor-pointer"
        >
            <template #empty>No data found.</template>

            <Column header="Customer">
                <template #body="{ data }">
                    {{ data.customer.first_name }} {{ data.customer.last_name }}
                    <span
                        v-if="data.customer.is_realtor"
                        class="ml-2 rounded bg-amber-100 px-1 py-0.5 text-xs text-amber-800"
                        >Realtor</span
                    >
                    <div class="text-xs text-gray-500">
                        {{ data.inspection.customer_role ?? '—' }}
                    </div>
                </template>
            </Column>

            <Column header="Contact">
                <template #body="{ data }">
                    <div class="max-w-64">
                        <div>
                            {{ data.customer.phone_1
                            }}<span v-if="data.customer.phone_2">
                                | {{ data.customer.phone_2 }}</span
                            >
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ data.customer.email_1
                            }}<span v-if="data.customer.email_2">
                                | {{ data.customer.email_2 }}</span
                            >
                        </div>
                    </div>
                </template>
            </Column>

            <Column header="Last Inspection">
                <template #body="{ data }">
                    <div>
                        #{{ data.inspection.id ?? '—' }} •
                        {{ data.inspection.date ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        <span v-if="data.inspection.months_since !== null"
                            >{{
                                new Intl.NumberFormat('en-US', {
                                    minimumFractionDigits: 1,
                                    maximumFractionDigits: 1,
                                }).format(data.inspection.months_since)
                            }}
                            mo ago</span
                        >
                        <span v-else>—</span>
                    </div>
                </template>
            </Column>

            <Column header="Fee">
                <template #body="{ data }">
                    {{ data.inspection.fee ?? '—' }}
                </template>
            </Column>

            <Column header="Property">
                <template #body="{ data }">
                    <div>{{ data.property.street_address }}</div>
                    <div class="text-xs text-gray-500">
                        {{ data.property.city }}, {{ data.property.state }} •
                        {{ data.property.property_type ?? '—' }}
                    </div>
                </template>
            </Column>

            <Column header="Sq Ft">
                <template #body="{ data }">
                    {{ data.property.square_footage ?? '—' }}
                </template>
            </Column>
        </DataTable>
    </AppLayout>
</template>
