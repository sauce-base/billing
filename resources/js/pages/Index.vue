<script setup lang="ts">
import Alert from '@/components/ui/alert/Alert.vue';
import AlertDescription from '@/components/ui/alert/AlertDescription.vue';
import AlertTitle from '@/components/ui/alert/AlertTitle.vue';

import Button from '@/components/ui/button/Button.vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

import IconInfo from '~icons/heroicons/information-circle';
import IconSparkles from '~icons/heroicons/sparkles';

interface Subscription {
    plan: string;
    price: string;
    interval: string;
    status: string;
    current_period_end: string;
    is_canceled: boolean;
    on_grace_period: boolean;
}

defineProps<{
    subscription: Subscription | null;
}>();

const title = 'Billing';
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="[{ title: title }]">
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <!-- Header Section -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground mt-1">
                        {{ $t('Manage your subscription and billing') }}
                    </p>
                </div>
                <div v-if="!subscription" class="flex gap-4">
                    <Link :href="route('billing.index')">
                        <Button>
                            <IconSparkles class="h-4 w-4" />
                            {{ $t('View Plans') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- No Subscription State -->
            <div v-if="!subscription">
                <Alert class="border-accent-foreground text-white">
                    <IconInfo />
                    <AlertTitle class="text-lg">{{
                        $t('No Active Subscription')
                    }}</AlertTitle>
                    <AlertDescription>
                        {{
                            $t(
                                'You do not have an active subscription. Choose a plan to get started!',
                            )
                        }}
                    </AlertDescription>
                </Alert>
            </div>
        </div>
    </AppLayout>
</template>
