<!-- resources/js/pages/customer/Show.vue -->
<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { show, update, index } from '@/routes/customer';
import { store } from '@/routes/customer/records';
import { Head, router, useForm } from '@inertiajs/vue3';
import { refresh } from '@/routes/proxy/latest-inspection';
import {
    Button,
    Card,
    Dialog,
    InputText,
    Select,
    Tag,
    Textarea,
} from 'primevue';
import { computed, ref } from 'vue';
import { type BreadcrumbItem } from '@/types';

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

type Contact = {
    id: number;
    contact_type: string;
    call_outcome?: string | null;
    call_direction?: string | null;
    occurred_at: string | null; // ISO
    notes?: string | null;
};

const props = defineProps<{
    customer: Customer;
    contacts: {
        data: Contact[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Customers',
        href: index().url,
    },
    {
        title: props.customer.full_name,
        href: '#'
    }
];

/* ---------- helpers ---------- */
function fmtPhone(raw: string | null): string | null {
    if (!raw) return null;
    const d = raw.replace(/\D+/g, '');
    if (d.length === 10)
        return `(${d.slice(0, 3)}) ${d.slice(3, 6)}-${d.slice(6)}`;
    if (d.length === 11 && d.startsWith('1'))
        return `+1 (${d.slice(1, 4)}) ${d.slice(4, 7)}-${d.slice(7)}`;
    return raw;
}
function fmtDT(iso: string | null): string | null {
    if (!iso) return null;
    const dt = new Date(iso);
    return isNaN(+dt)
        ? iso
        : dt.toLocaleString('en-US', {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}
/* ---------- call / email helpers ---------- */
function onlyDigits(raw: string | null): string | null {
    if (!raw) return null;
    const d = raw.replace(/\D+/g, '');
    if (d.length === 10) return `1${d}`; // normalize to E.164-ish for US
    if (d.length === 11 && d.startsWith('1')) return d;
    return d || null;
}
const primaryPhoneDigits = computed(() => onlyDigits(props.customer.phone_1));
const smsHref = computed(() =>
    primaryPhoneDigits.value ? `sms:+${primaryPhoneDigits.value}` : null,
);
const telHref = computed(() =>
    primaryPhoneDigits.value ? `tel:+${primaryPhoneDigits.value}` : null,
);
const mailHref = computed(() =>
    props.customer.email_1
        ? `mailto:${encodeURIComponent(props.customer.email_1)}`
        : null,
);

/* ---------- left column: latest inspection view model ---------- */
const latest = computed(() => {
    const li = props.customer.latest_inspection ?? {};
    const p = li.property ?? {};
    return {
        id: li.id ?? null,
        dateLabel:
            li.date ??
            (li.date_raw ? new Date(li.date_raw).toLocaleDateString() : null),
        feeLabel: li.fee ?? null,
        general: !!li.general,
        mitigation: !!li.mitigation,
        four_point: !!li.four_point,
        property: {
            type: p.property_type ?? null,
            address: [p.street_address].filter(Boolean).join(' ') || null,
            cityState: [p.city, p.state].filter(Boolean).join(', ') || null,
            sqft: p.square_footage ? `${p.square_footage} sq ft` : null,
        },
    };
});

/* ---------- edit social links form (PUT /customer/{id}) ---------- */
// const social = useForm<{ social_media_links: Record<string, string> }>({
//     social_media_links: { ...(props.customer.social_media_links ?? {}) },
// });

/* ---------- right column: log contact dialog ---------- */
const isDialogVisible = ref(false);
type ContactForm = {
    contact_type: string;
    call_outcome?: string;
    call_direction?: string;
    date: string;
    time: string;
    notes: string;
};
const contactTypeOptions = [
    { label: 'Phone Call', value: 'phone_call' },
    { label: 'Text Message', value: 'text_message' },
    { label: 'Email', value: 'email' },
    { label: 'Mail', value: 'mail' },
];
const callOutcomeOptions = [
    { label: 'Busy', value: 'busy' },
    { label: 'Connected', value: 'connected' },
    { label: 'Left Voicemail', value: 'left_voicemail' },
    { label: 'No Answer', value: 'no_answer' },
    { label: 'Wrong Number', value: 'wrong_number' },
];
const callDirectionOptions = [
    { label: 'Inbound', value: 'inbound' },
    { label: 'Outbound', value: 'outbound' },
];

const contactForm = useForm<ContactForm>({
    contact_type: '',
    call_outcome: '',
    call_direction: '',
    date: new Date().toISOString().slice(0, 10),
    time: new Date().toTimeString().slice(0, 5),
    notes: '',
});

const socialMediaPlatformOptions = [
    { label: 'Facebook', value: 'facebook' },
    { label: 'Instagram', value: 'instagram' },
    { label: 'X', value: 'x' },
    { label: 'LinkedIn', value: 'linkedin' },
    { label: 'TikTok', value: 'tiktok' },
    { label: 'YouTube', value: 'youtube' },
    { label: 'Other', value: 'other' },
];

// Edit as an array of rows
type SocialRow = { platform: string; url: string };
const socialRows = ref<SocialRow[]>([]);

// Initialize from the object on props.customer.social_media_links
// (object like { facebook: "https://...", instagram: "https://..." })
const initialObj = props.customer.social_media_links ?? {};
socialRows.value = Object.entries(initialObj).map(([platform, url]) => ({
    platform,
    url: String(url ?? ''),
}));

function addSocialRow() {
    socialRows.value.push({ platform: '', url: '' });
}
function removeSocialRow(idx: number) {
    socialRows.value.splice(idx, 1);
}

function saveSocial() {
    // Convert rows -> object; drop empty/invalid rows
    const payload: Record<string, string> = {};
    for (const row of socialRows.value) {
        const key = row.platform?.trim();
        const val = row.url?.trim();
        if (key && val) payload[key] = val;
    }
    router.put(
        update(props.customer.id),
        { social_media_links: payload },
        { preserveScroll: true },
    );
}

function submitContact() {

    contactForm.post(store(props.customer.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            isDialogVisible.value = false;
            contactForm.reset(
                'contact_type',
                'call_outcome',
                'call_direction',
                'notes'
            );
            contactForm.date = new Date().toISOString().slice(0, 10);
            contactForm.time = new Date().toTimeString().slice(0, 5);
        }
    })
}

const refreshing = ref(false)

function onRefreshLatestInspection() {
  refreshing.value = true
  router.post(
    refresh(), // or refresh(props.customer.id) if your helper requires an id
    {
      customer_id: props.customer.id,     // new DB id
      legacy_id:   props.customer.legacy_id, // legacy id (important)
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        // pull the freshly-updated customer from the server
        router.reload({ only: ['customer'] })
        console.log('Latest inspection refreshed!')
      },
      onError: (errors) => {
        console.error('Refresh failed', errors)
      },
      onFinish: () => {
        refreshing.value = false
      },
    }
  )
}

</script>

<template>
    <Head
        :title="`Customer • ${customer.full_name || `${customer.first_name} ${customer.last_name}`}`"
    />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <Heading
                :title="
                    customer.full_name ||
                    `${customer.first_name ?? ''} ${customer.last_name ?? ''}`.trim()
                "
                :description="`Legacy #${customer.legacy_id}`"
            >
                <template #right>
                    <div class="flex items-center gap-2">
                        <Tag
                            v-if="customer.is_realtor"
                            value="Realtor"
                            severity="warning"
                        />
                        <Tag
                            v-if="customer.last_contact_at"
                            :value="`Last contact: ${fmtDT(customer.last_contact_at)}${customer.last_contact_type ? ' • ' + customer.last_contact_type : ''}`"
                            severity="info"
                        />
                    </div>
                </template>
            </Heading>

            <Card>
                <template #content>
                    <div class="flex justify-end flex-col lg:flex-row gap-3">
                        <Button
                            label="Call"
                            icon="pi pi-phone"
                            :disabled="!telHref"
                            as="a"
                            :href="telHref || undefined"
                        />
                        <Button
                            label="Text"
                            icon="pi pi-comment"
                            severity="info"
                            :disabled="!smsHref"
                            as="a"
                            :href="smsHref || undefined"
                        />
                        <Button
                            label="Email"
                            icon="pi pi-envelope"
                            severity="secondary"
                            :disabled="!mailHref"
                            as="a"
                            :href="mailHref || undefined"
                        />
                        <Button
                            label="Refresh Latest Inspection"
                            icon="pi pi-refresh"
                            severity="secondary"
                            @click="onRefreshLatestInspection"
                        />
                    </div>
                </template>
            </Card>

            <!-- 2-column layout; Left: Contact + Latest Inspection + Social editor; Right: Contact Records -->
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <!-- LEFT COLUMN -->
                <div class="flex flex-col gap-5">
                    <!-- Contact -->
                    <!-- LEFT COLUMN -->
                    <div class="flex flex-col gap-5">
                        <!-- Contact + Latest side-by-side -->
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <!-- Contact -->
                            <Card>
                                <template #title>
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <span>Contact</span>
                                        <div
                                            class="flex items-center gap-2"
                                        ></div>
                                    </div>
                                </template>
                                <template #content>
                                    <div
                                        class="mt-3 flex flex-col gap-3 text-sm"
                                    >
                                        <div class="flex justify-between">
                                            <span class="text-gray-500"
                                                >Primary Phone</span
                                            >
                                            <span class="font-medium">{{
                                                fmtPhone(customer.phone_1) ||
                                                '—'
                                            }}</span>
                                        </div>

                                        <div class="flex justify-between">
                                            <span class="text-gray-500"
                                                >Secondary Phone</span
                                            >
                                            <span class="font-medium">{{
                                                fmtPhone(customer.phone_2) ||
                                                '—'
                                            }}</span>
                                        </div>

                                        <div
                                            class="col-span-2 flex justify-between"
                                        >
                                            <span class="text-gray-500"
                                                >Primary Email</span
                                            >
                                            <span
                                                class="font-medium break-all"
                                                >{{
                                                    customer.email_1 || '—'
                                                }}</span
                                            >
                                        </div>

                                        <div
                                            class="col-span-2 flex justify-between"
                                        >
                                            <span class="text-gray-500"
                                                >Secondary Email</span
                                            >
                                            <span
                                                class="font-medium break-all"
                                                >{{
                                                    customer.email_2 || '—'
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </template>
                            </Card>

                            <!-- Latest Inspection -->
                            <Card>
                                <template #title>Latest Inspection</template>
                                <template #content>
                                    <div class="flex flex-col gap-3">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <div class="text-sm">
                                                <div class="font-medium">
                                                    ID: {{ latest.id ?? '—' }}
                                                </div>
                                                <div class="text-gray-500">
                                                    Date:
                                                    {{
                                                        latest.dateLabel ?? '—'
                                                    }}
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div
                                                    class="text-sm text-gray-500"
                                                >
                                                    Fee
                                                </div>
                                                <div
                                                    class="text-lg font-semibold"
                                                >
                                                    {{ latest.feeLabel ?? '—' }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Tag
                                                v-if="latest.general"
                                                value="General"
                                                severity="success"
                                            />
                                            <Tag
                                                v-if="latest.mitigation"
                                                value="Wind Mit"
                                                severity="info"
                                            />
                                            <Tag
                                                v-if="latest.four_point"
                                                value="4-Point"
                                                severity="warning"
                                            />
                                        </div>
                                        <div class="rounded border p-3">
                                            <div
                                                class="mb-2 flex items-center justify-between"
                                            >
                                                <span class="font-semibold"
                                                    >Property</span
                                                >
                                                <Tag
                                                    :value="
                                                        latest.property.type ??
                                                        '—'
                                                    "
                                                    severity="info"
                                                />
                                            </div>
                                            <div class="text-sm">
                                                <div>
                                                    {{
                                                        latest.property
                                                            .address || '—'
                                                    }}
                                                </div>
                                                <div class="text-gray-500">
                                                    {{
                                                        latest.property
                                                            .cityState || '—'
                                                    }}
                                                    <span
                                                        v-if="
                                                            latest.property.sqft
                                                        "
                                                        class=""
                                                        >•
                                                        {{
                                                            latest.property.sqft
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Card>
                        </div>

                        <!-- Social links editor (full width under the two cards) -->
                        <Card>
                            <template #title>Social Links</template>
                            <template #content>
                                <div class="space-y-3">
                                    <div
                                        v-for="(row, idx) in socialRows"
                                        :key="idx"
                                        class="grid grid-cols-12 items-center gap-2"
                                    >
                                        <div class="col-span-4">
                                            <Select
                                                :options="
                                                    socialMediaPlatformOptions
                                                "
                                                optionLabel="label"
                                                optionValue="value"
                                                v-model="row.platform"
                                                placeholder="Select a platform"
                                            />
                                        </div>
                                        <div class="col-span-7">
                                            <InputText
                                                fluid
                                                v-model="row.url"
                                                placeholder="https://example.com/your-profile"
                                            />
                                        </div>
                                        <div
                                            class="col-span-1 flex justify-end"
                                        >
                                            <Button
                                                icon="pi pi-trash"
                                                severity="danger"
                                                outlined
                                                @click="removeSocialRow(idx)"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Button
                                            label="Add row"
                                            icon="pi pi-plus"
                                            outlined
                                            @click="addSocialRow"
                                        />
                                        <Button
                                            label="Save"
                                            icon="pi pi-check"
                                            @click="saveSocial"
                                        />
                                    </div>
                                </div>
                            </template>
                        </Card>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Contact Records (same column width as Latest Inspection) -->
                <div class="flex flex-col gap-4 sm:gap-5">
                    <Card>
                        <template #title>
                            <!-- TITLE BAR: stack on xs, row on sm+ -->
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="text-base sm:text-lg">Contact Records</span>

                                <!-- BUTTON: full-width on xs, auto on sm+ -->
                                <Button
                                    label="Log Contact"
                                    icon="pi pi-pen-to-square"
                                    size="small"
                                    class="w-full sm:w-auto"
                                    @click="isDialogVisible = true"
                                />
                            </div>
                        </template>

                        <template #content>
                            <div v-if="props.contacts.data.length" class="space-y-2 sm:space-y-3">

                                <div
                                    v-for="rec in props.contacts.data"
                                    :key="rec.id"
                                    class="rounded border p-2 sm:p-3 hover:bg-gray-900/5 dark:hover:bg-gray-50/5"
                                >
                                <!-- RECORD HEADER: stack on xs, grid on sm+ so date can sit right -->
                                <div class="mb-1 grid gap-1 sm:grid-cols-[1fr_auto] sm:items-center">
                                    <!-- Left group: wrap tags/labels on small screens -->
                                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                    <Tag
                                        :value="rec.contact_type.replace('_',' ')"
                                        :severity="
                                        rec.contact_type === 'phone_call'
                                            ? 'success'
                                            : rec.contact_type === 'text_message'
                                            ? 'info'
                                            : rec.contact_type === 'email'
                                                ? 'warn'
                                                : 'secondary'
                                        "
                                        class="text-xs sm:text-[0.8rem]"
                                    />
                                    <span v-if="rec.call_direction" class="text-[11px] sm:text-xs text-gray-500">
                                        {{ rec.call_direction }}
                                    </span>
                                    <span v-if="rec.call_outcome" class="text-[11px] sm:text-xs text-gray-500">
                                        • {{ rec.call_outcome }}
                                    </span>
                                    </div>

                                    <!-- Date: moves below or right depending on width -->
                                    <div class="text-[11px] sm:text-sm text-gray-400 sm:text-right">
                                        {{ fmtDT(rec.occurred_at) }}
                                    </div>
                                </div>

                                    <!-- NOTES: allow wrap on mobile, slightly smaller text -->
                                    <div v-if="rec.notes" class="break-words text-[13px] sm:text-sm text-gray-700 dark:text-gray-300">
                                        {{ rec.notes }}
                                    </div>
                                </div>

                                <!-- PAGINATION: stack on xs; buttons full-width on xs -->
                                <div class="mt-2 flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end">
                                    <div class="flex gap-2 sm:order-2">
                                        <Button
                                        label="Prev"
                                        :disabled="props.contacts.current_page <= 1"
                                        size="small"
                                        outlined
                                        class="flex-1 sm:flex-none w-full sm:w-auto"
                                        @click="router.get(
                                            show(customer.id).url,
                                            { page: props.contacts.current_page - 1 },
                                            { preserveScroll: true },
                                        )"
                                        />
                                        <Button
                                        label="Next"
                                        :disabled="props.contacts.current_page >= props.contacts.last_page"
                                        size="small"
                                        outlined
                                        class="flex-1 sm:flex-none w-full sm:w-auto"
                                        @click="router.get(
                                            show(customer.id).url,
                                            { page: props.contacts.current_page + 1 },
                                            { preserveScroll: true },
                                        )"
                                        />
                                    </div>

                                    <span class="text-center sm:text-right text-[11px] sm:text-xs text-gray-500 sm:order-1">
                                        Page {{ props.contacts.current_page }} / {{ props.contacts.last_page }}
                                    </span>
                                </div>
                            </div>

                            <div v-else class="text-sm sm:text-base text-gray-500">
                                No contacts yet.
                            </div>
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Log Contact dialog -->
            <Dialog
                v-model:visible="isDialogVisible"
                modal
                header="Log Contact"
                :style="{ width: '40rem' }"
            >
                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col">
                            <label class="mb-1 text-xs text-gray-500"
                                >Date</label
                            >
                            <InputText type="date" v-model="contactForm.date" />
                        </div>
                        <div class="flex flex-col">
                            <label class="mb-1 text-xs text-gray-500"
                                >Time</label
                            >
                            <InputText type="time" v-model="contactForm.time" />
                        </div>
                        <div class="flex flex-col">
                            <label class="mb-1 text-xs text-gray-500"
                                >Type</label
                            >
                            <Select
                                :options="contactTypeOptions"
                                optionLabel="label"
                                optionValue="value"
                                v-model="contactForm.contact_type"
                            />
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3"
                        v-if="contactForm.contact_type === 'phone_call'"
                    >
                        <div class="flex flex-col">
                            <label class="mb-1 text-xs text-gray-500"
                                >Outcome</label
                            >
                            <Select
                                :options="callOutcomeOptions"
                                optionLabel="label"
                                optionValue="value"
                                v-model="contactForm.call_outcome"
                            />
                        </div>
                        <div class="flex flex-col">
                            <label class="mb-1 text-xs text-gray-500"
                                >Direction</label
                            >
                            <Select
                                :options="callDirectionOptions"
                                optionLabel="label"
                                optionValue="value"
                                v-model="contactForm.call_direction"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <label class="mb-1 text-xs text-gray-500">Notes</label>
                        <Textarea rows="6" v-model="contactForm.notes" />
                    </div>

                    <div class="flex justify-end">
                        <Button
                            label="Save"
                            icon="pi pi-check"
                            @click="submitContact"
                        />
                    </div>
                </div>
            </Dialog>
        </div>
    </AppLayout>
</template>
