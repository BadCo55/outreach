<!-- resources/js/pages/Dashboard.vue -->
<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { store } from '@/routes/customer';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Button,
    Card,
    Dialog,
    InputText,
    Select,
    Tag,
    Textarea,
} from 'primevue';
import { ref } from 'vue';

type SourceCustomer = {
    id: number | null;
    first_name: string | null;
    last_name: string | null;
    phone_1: string | null;
    phone_2: string | null;
    email_1: string | null;
    email_2: string | null;
    is_realtor: boolean | null;
};

type SourceInspection = {
    id: number | null;
    customer_role: string | null;
    date: string | null;
    date_raw: string | null;
    fee: string | null;
    general: boolean | null;
    mitigation: boolean | null;
    four_point: boolean | null;
};

type SourceProperty = {
    id: number | null;
    property_type: string | null;
    street_address: string | null;
    city: string | null;
    state: string | null;
    square_footage: string | null;
};

type ContactRecord = {
    contact_type: string;
    call_outcome?: string;
    call_direction?: string;
    date: string;
    time: string;
    notes: string;
};

// Your strict form payload (what you POST) — as you wrote
type CustomerForm = {
    token: string;
    legacy_customer_id: number;

    customer: {
        first_name: string;
        last_name: string;
        email_1: string;
        email_2: string;
        phone_1: string;
        phone_2: string;
        is_realtor: boolean;
    };

    // required object; fields can be null
    property: {
        id: number | null;
        property_type: string | null;
        street_address: string | null;
        city: string | null;
        state: string | null;
        square_footage: string | null;
    };

    // required object; fields can be null
    inspection: {
        id: number | null;
        customer_role: string | null;
        date: string | null;
        date_raw: string | null;
        fee: string | null;
        general: boolean | null;
        mitigation: boolean | null;
        four_point: boolean | null;
    };

    contact_records: ContactRecord[];
};

type ContactForm = {
    contact_type: string;
    call_outcome?: string;
    call_direction?: string;
    date: string;
    time: string;
    notes: string;
};

const EMPTY_PROPERTY: CustomerForm['property'] = {
    id: null,
    property_type: null,
    street_address: null,
    city: null,
    state: null,
    square_footage: null,
};

const EMPTY_INSPECTION: CustomerForm['inspection'] = {
    id: null,
    customer_role: null,
    date: null,
    date_raw: null,
    fee: null,
    general: null,
    mitigation: null,
    four_point: null,
};

function buildForm(initial: {
    token: string;
    customer: SourceCustomer;
    property?: SourceProperty;
    inspection?: SourceInspection;
}): CustomerForm {
    return {
        token: initial.token,
        legacy_customer_id: initial.customer.id ?? 0,

        customer: {
            first_name: initial.customer.first_name ?? '',
            last_name: initial.customer.last_name ?? '',
            email_1: initial.customer.email_1 ?? '',
            email_2: initial.customer.email_2 ?? '',
            phone_1: initial.customer.phone_1 ?? '',
            phone_2: initial.customer.phone_2 ?? '',
            is_realtor: !!initial.customer.is_realtor,
        },

        property: initial.property
            ? {
                  id: initial.property.id ?? null,
                  property_type: initial.property.property_type ?? null,
                  street_address: initial.property.street_address ?? null,
                  city: initial.property.city ?? null,
                  state: initial.property.state ?? null,
                  square_footage: initial.property.square_footage ?? null,
              }
            : { ...EMPTY_PROPERTY },

        inspection: initial.inspection
            ? {
                  id: initial.inspection.id ?? null,
                  customer_role: initial.inspection.customer_role ?? null,
                  date: initial.inspection.date ?? null,
                  date_raw: initial.inspection.date_raw ?? null,
                  fee: initial.inspection.fee ?? null,
                  general: initial.inspection.general ?? null,
                  mitigation: initial.inspection.mitigation ?? null,
                  four_point: initial.inspection.four_point ?? null,
              }
            : { ...EMPTY_INSPECTION },

        contact_records: [],
    };
}

// Props (unchanged)
const props = defineProps<{
    token: string;
    initial: {
        customer: SourceCustomer;
        inspection: SourceInspection;
        property: SourceProperty;
    };
}>();

const form = useForm<CustomerForm>(
    buildForm({
        token: props.token,
        customer: props.initial.customer,
        property: props.initial.property,
        inspection: props.initial.inspection,
    }),
);

const contactForm = ref<ContactForm>({
    contact_type: '',
    call_outcome: '',
    call_direction: '',
    date: new Date().toISOString().slice(0, 10), // "YYYY-MM-DD"
    time: new Date().toTimeString().slice(0, 5), // "HH:MM"
    notes: '',
});

const isDialogVisible = ref(false);

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

function onSubmitContact() {
    form.contact_records.push({ ...contactForm.value });

    // reset the dialog form
    Object.assign(contactForm.value, {
        contact_type: '',
        call_outcome: '',
        call_direction: '',
        date: new Date().toISOString().slice(0, 10),
        time: new Date().toTimeString().slice(0, 5),
        notes: '',
    });
    isDialogVisible.value = false;
}

function onSubmitForm() {
    form.post(store().url, {
        onSuccess: (res) => {
            console.log(res);
        },
        onError: (err) => {
            console.log(err);
        },
    });
}
</script>

<template>
    <Head title="Create Customer" />
    <AppLayout>
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-hidden rounded-xl p-3 sm:p-4"
        >
            <Heading
                title="Create a Customer"
                description="Create a new customer or one from an existing resource"
            />

            <!-- FORM GRID: 1 col on mobile, 2 cols on md+ -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:gap-6">
                <!-- LEFT COLUMN -->
                <div>
                    <!-- INPUT GRID: 1 col on xs, 2 cols on sm+ -->
                    <div
                        class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4"
                    >
                        <div class="flex flex-col">
                            <label
                                for="first_name"
                                class="text-xs sm:text-sm dark:text-gray-400"
                                >First Name</label
                            >
                            <InputText
                                v-model="form.customer.first_name"
                                id="first_name"
                                name="first_name"
                                class="w-full"
                            />
                        </div>

                        <div class="flex flex-col">
                            <label
                                for="last_name"
                                class="text-xs sm:text-sm dark:text-gray-400"
                                >Last Name</label
                            >
                            <InputText
                                v-model="form.customer.last_name"
                                id="last_name"
                                name="last_name"
                                class="w-full"
                            />
                        </div>

                        <div class="flex flex-col">
                            <label
                                for="phone_1"
                                class="text-xs sm:text-sm dark:text-gray-400"
                                >Phone 1</label
                            >
                            <InputText
                                v-model="form.customer.phone_1"
                                id="phone_1"
                                name="phone_1"
                                class="w-full"
                            />
                        </div>

                        <div class="flex flex-col">
                            <label
                                for="phone_2"
                                class="text-xs sm:text-sm dark:text-gray-400"
                                >Phone 2</label
                            >
                            <InputText
                                v-model="form.customer.phone_2"
                                id="phone_2"
                                name="phone_2"
                                class="w-full"
                            />
                        </div>

                        <div class="flex flex-col">
                            <label
                                for="email_1"
                                class="text-xs sm:text-sm dark:text-gray-400"
                                >Email 1</label
                            >
                            <InputText
                                v-model="form.customer.email_1"
                                id="email_1"
                                name="email_1"
                                class="w-full"
                            />
                        </div>

                        <div class="flex flex-col">
                            <label
                                for="email_2"
                                class="text-xs sm:text-sm dark:text-gray-400"
                                >Email 2</label
                            >
                            <InputText
                                v-model="form.customer.email_2"
                                id="email_2"
                                name="email_2"
                                class="w-full"
                            />
                        </div>
                    </div>

                    <Card>
                        <template #title> Last Inspection </template>
                        <template #content>
                            <div class="flex flex-col gap-2">
                                <!-- ROWS: stack label/value on xs, space-between on sm+ -->
                                <div
                                    class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="text-sm text-gray-500">ID:</div>
                                    <div class="text-sm">
                                        {{ form.inspection.id }}
                                    </div>
                                </div>

                                <div
                                    class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="text-sm text-gray-500">
                                        Date:
                                    </div>
                                    <div class="text-sm">
                                        {{ form.inspection.date }}
                                    </div>
                                </div>

                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="text-sm text-gray-500">
                                        Inspection Type(s):
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Tag
                                            v-if="form.inspection.general"
                                            value="General"
                                        />
                                        <Tag
                                            v-if="form.inspection.mitigation"
                                            value="Wind Mit"
                                        />
                                        <Tag
                                            v-if="form.inspection.four_point"
                                            value="4-Point"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="text-sm text-gray-500">
                                        Fee:
                                    </div>
                                    <div class="text-sm">
                                        {{ form.inspection.fee }}
                                    </div>
                                </div>

                                <div class="rounded border p-3">
                                    <div class="flex flex-col gap-2">
                                        <div
                                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <p class="font-semibold">
                                                Property
                                            </p>
                                            <Tag
                                                :value="
                                                    form.property.property_type
                                                "
                                                severity="info"
                                            />
                                        </div>

                                        <!-- ADDRESS LINE: wraps on mobile; tags don’t overflow -->
                                        <div
                                            class="flex flex-col gap-2 break-words sm:flex-row sm:justify-between"
                                        >
                                            <div class="text-sm">
                                                {{
                                                    form.property
                                                        .street_address
                                                }}, {{ form.property.city }},
                                                {{ form.property.state }}
                                            </div>
                                            <Tag
                                                :value="`${form.property.square_footage} sq. ft.`"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Card>

                    <!-- ACTION BUTTONS: full-width on mobile -->
                    <div class="mt-5 flex">
                        <Button
                            label="Save Customer"
                            icon="pi pi-check-circle"
                            class="w-full sm:w-auto"
                            @click="onSubmitForm"
                        />
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div>
                    <!-- Quick Actions: stack on xs -->
                    <div
                        class="flex flex-col gap-2 rounded-lg border p-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex gap-2">
                            <Button
                                label="Call"
                                icon="pi pi-phone"
                                as="a"
                                :href="`tel:+1${form.customer.phone_1}`"
                                class="w-full sm:w-auto"
                            />
                            <Button
                                label="Email"
                                icon="pi pi-envelope"
                                as="a"
                                :href="`mailto:${form.customer.email_1}`"
                                class="w-full sm:w-auto"
                            />
                        </div>

                        <div class="flex gap-2">
                            <Button
                                label="Log Contact"
                                severity="info"
                                icon="pi pi-pen-to-square"
                                class="w-full sm:w-auto"
                                @click="isDialogVisible = true"
                            />

                            <!-- Dialog: responsive width via breakpoints -->
                            <Dialog
                                v-model:visible="isDialogVisible"
                                modal
                                header="Log Contact"
                                :breakpoints="{
                                    '960px': '75vw',
                                    '640px': '95vw',
                                }"
                                :style="{ width: '40rem' }"
                            >
                                <div class="flex flex-col gap-4">
                                    <!-- Date/Time grid: 1 col on xs, 3 cols only if space -->
                                    <div
                                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                                    >
                                        <div class="flex flex-col">
                                            <label
                                                for="date"
                                                class="text-xs sm:text-sm dark:text-gray-400"
                                                >Date</label
                                            >
                                            <InputText
                                                type="date"
                                                v-model="contactForm.date"
                                                id="date"
                                                name="date"
                                                class="w-full"
                                            />
                                        </div>
                                        <div class="flex flex-col">
                                            <label
                                                for="time"
                                                class="text-xs sm:text-sm dark:text-gray-400"
                                                >Time</label
                                            >
                                            <InputText
                                                type="time"
                                                v-model="contactForm.time"
                                                id="time"
                                                name="time"
                                                class="w-full"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                                    >
                                        <div class="flex flex-col">
                                            <label
                                                for="contact_type"
                                                class="text-xs sm:text-sm dark:text-gray-400"
                                                >Contact Type</label
                                            >
                                            <Select
                                                :options="contactTypeOptions"
                                                option-label="label"
                                                option-value="value"
                                                name="contact_type"
                                                id="contact_type"
                                                v-model="
                                                    contactForm.contact_type
                                                "
                                                class="w-full"
                                            />
                                        </div>

                                        <div
                                            class="flex flex-col"
                                            v-if="
                                                contactForm.contact_type ===
                                                'phone_call'
                                            "
                                        >
                                            <label
                                                for="call_outcome"
                                                class="text-xs sm:text-sm dark:text-gray-400"
                                                >Call Outcome</label
                                            >
                                            <Select
                                                :options="callOutcomeOptions"
                                                option-label="label"
                                                option-value="value"
                                                name="call_outcome"
                                                id="call_outcome"
                                                v-model="
                                                    contactForm.call_outcome
                                                "
                                                class="w-full"
                                            />
                                        </div>

                                        <div
                                            class="flex flex-col"
                                            v-if="
                                                contactForm.contact_type ===
                                                'phone_call'
                                            "
                                        >
                                            <label
                                                for="call_direction"
                                                class="text-xs sm:text-sm dark:text-gray-400"
                                                >Call Direction</label
                                            >
                                            <Select
                                                :options="callDirectionOptions"
                                                option-label="label"
                                                option-value="value"
                                                name="call_direction"
                                                id="call_direction"
                                                v-model="
                                                    contactForm.call_direction
                                                "
                                                class="w-full"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex w-full flex-col">
                                        <label
                                            for="notes"
                                            class="text-xs sm:text-sm dark:text-gray-400"
                                            >Notes</label
                                        >
                                        <Textarea
                                            class="w-full"
                                            rows="6"
                                            v-model="contactForm.notes"
                                            name="notes"
                                            id="notes"
                                        />

                                        <div
                                            class="flex flex-col gap-2 sm:flex-row sm:justify-end"
                                        >
                                            <Button
                                                label="Submit"
                                                type="button"
                                                class="w-full sm:w-auto"
                                                @click="onSubmitContact"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </Dialog>
                        </div>
                    </div>

                    <!-- Logged Contacts -->
                    <div v-if="form.contact_records?.length" class="mt-6">
                        <h3 class="mb-3 text-base font-semibold sm:text-lg">
                            Logged Contacts ({{ form.contact_records?.length }})
                        </h3>

                        <div class="flex flex-col gap-3">
                            <div
                                v-for="(record, index) in form.contact_records"
                                :key="index"
                                class="rounded-lg border p-3 shadow-sm transition hover:bg-gray-900/10 sm:p-4 dark:hover:bg-gray-50/5"
                            >
                                <!-- header -->
                                <div
                                    class="mb-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Tag
                                            :value="
                                                contactTypeOptions.find(
                                                    (opt) =>
                                                        opt.value ===
                                                        record.contact_type,
                                                )?.label || record.contact_type
                                            "
                                            :severity="
                                                record.contact_type ===
                                                'phone_call'
                                                    ? 'success'
                                                    : record.contact_type ===
                                                        'text_message'
                                                      ? 'info'
                                                      : record.contact_type ===
                                                          'email'
                                                        ? 'warn'
                                                        : 'secondary'
                                            "
                                        />
                                    </div>
                                    <div
                                        class="text-xs sm:text-sm dark:text-gray-400"
                                    >
                                        {{ record.date }} •
                                        {{
                                            new Date(
                                                `${record.date}T${record.time}`,
                                            ).toLocaleTimeString('en-US', {
                                                hour: 'numeric',
                                                minute: '2-digit',
                                            })
                                        }}
                                    </div>
                                </div>

                                <!-- body -->
                                <div
                                    class="space-y-1 text-[13px] sm:text-sm dark:text-gray-400"
                                >
                                    <div
                                        v-if="
                                            record.call_outcome ||
                                            record.call_direction
                                        "
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1"
                                    >
                                        <template v-if="record.call_direction">
                                            <span class="font-medium"
                                                >Direction:</span
                                            >
                                            <span>
                                                {{
                                                    callDirectionOptions.find(
                                                        (opt) =>
                                                            opt.value ===
                                                            record.call_direction,
                                                    )?.label ||
                                                    record.call_direction
                                                }}
                                            </span>
                                        </template>

                                        <template v-if="record.call_outcome">
                                            <span class="font-medium"
                                                >Outcome:</span
                                            >
                                            <span>
                                                {{
                                                    callOutcomeOptions.find(
                                                        (opt) =>
                                                            opt.value ===
                                                            record.call_outcome,
                                                    )?.label ||
                                                    record.call_outcome
                                                }}
                                            </span>
                                        </template>
                                    </div>

                                    <div
                                        v-if="record.notes"
                                        class="rounded border p-2 dark:bg-gray-800/10"
                                    >
                                        <span class="ml-1">{{
                                            record.notes
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Logged Contacts -->
                </div>
                <!-- /RIGHT COLUMN -->
            </div>
            <!-- /FORM GRID -->
        </div>
    </AppLayout>
</template>
