<script setup lang="ts">
import Alert from '@/components/ui/alert/Alert.vue';
import AlertDescription from '@/components/ui/alert/AlertDescription.vue';
import AlertTitle from '@/components/ui/alert/AlertTitle.vue';

import Button from '@/components/ui/button/Button.vue';

import AppLayout from '@/layouts/AppLayout.vue';

import IconInfo from '~icons/heroicons/information-circle';
import IconSparkles from '~icons/heroicons/sparkles';

import type { Price, Product } from '@modules/Billing/resources/js/types';

interface Subscription {
    id: number;
    status: string;
    current_period_starts_at: string | null;
    current_period_ends_at: string | null;
    cancelled_at: string | null;
    ends_at: string | null;
    price: Price & { product: Product };
}

interface Purchase {
    id: number;
    amount: number;
    currency: string;
    status: string;
    created_at: string;
    price: Price & { product: Product };
}

defineProps<{
    subscription: Subscription | null;
    purchase: Purchase | null;
    products: Product[];
}>();

function formatDate(date: string | null): string {
    if (!date) return '';
    return new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

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
                <div class="flex gap-4">
                    <a v-if="subscription" :href="route('billing.portal')">
                        <Button>
                            {{ $t('Manage Subscription') }}
                        </Button>
                    </a>
                    <a v-else href="/#pricing">
                        <Button>
                            <IconSparkles class="h-4 w-4" />
                            {{ $t('View Plans') }}
                        </Button>
                    </a>
                </div>
            </div>

            <!-- Active Subscription -->
            <div v-if="subscription">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h2
                                class="text-xl font-semibold text-gray-900 dark:text-white"
                            >
                                {{ subscription.price.product.name }}
                            </h2>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                v-html="subscription.price.product.description"
                            />
                        </div>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="
                                subscription.status === 'active'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                            "
                        >
                            {{ subscription.status }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div v-if="subscription.current_period_ends_at">
                            <p
                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('Renews On') }}
                            </p>
                            <p
                                class="mt-1 text-sm text-gray-900 dark:text-white"
                            >
                                {{
                                    formatDate(
                                        subscription.current_period_ends_at,
                                    )
                                }}
                            </p>
                        </div>
                        <div v-if="subscription.cancelled_at">
                            <p
                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('Cancels On') }}
                            </p>
                            <p
                                class="mt-1 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ formatDate(subscription.ends_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- One-Time Purchase -->
            <div v-if="purchase?.price?.product">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <h2
                                class="text-xl font-semibold text-gray-900 dark:text-white"
                            >
                                {{ purchase.price.product.name }}
                            </h2>
                            <p
                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                v-html="purchase.price.product.description"
                            />
                        </div>
                        <span
                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            {{ $t('Purchased') }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <p
                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('Amount') }}
                            </p>
                            <p
                                class="mt-1 text-sm text-gray-900 dark:text-white"
                            >
                                ${{ (purchase.amount / 100).toFixed(2) }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ $t('Purchase Date') }}
                            </p>
                            <p
                                class="mt-1 text-sm text-gray-900 dark:text-white"
                            >
                                {{ formatDate(purchase.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Subscription or Purchase State -->
            <div v-if="!subscription && !purchase">
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
