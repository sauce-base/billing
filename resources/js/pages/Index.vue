<script setup lang="ts">
import Alert from '@/components/ui/alert/Alert.vue';
import AlertDescription from '@/components/ui/alert/AlertDescription.vue';
import AlertTitle from '@/components/ui/alert/AlertTitle.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardFooter from '@/components/ui/card/CardFooter.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

import IconCreditCard from '~icons/heroicons/credit-card';
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

const props = defineProps<{
    subscription: Subscription | null;
}>();

const title = 'Billing';

const cancelSubscription = (subscriptionId: number) => {
    if (
        confirm(
            'Are you sure you want to cancel your subscription? You will have access until the end of your billing period.',
        )
    ) {
        router.delete(route('billing.subscriptions.destroy', subscriptionId));
    }
};

const resumeSubscription = (subscriptionId: number) => {
    router.post(route('billing.subscriptions.resume', subscriptionId));
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'canceled':
            return 'secondary';
        case 'past_due':
            return 'destructive';
        default:
            return 'outline';
    }
};
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
                    <Link :href="route('billing.plans')">
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

                <Card class="mt-6">
                    <CardHeader>
                        <CardTitle>{{ $t('Get Started') }}</CardTitle>
                        <CardDescription>
                            {{
                                $t(
                                    'Choose a plan that fits your needs and start today.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardFooter>
                        <Link :href="route('billing.plans')">
                            <Button>
                                <IconSparkles class="h-4 w-4" />
                                {{ $t('View Plans') }}
                            </Button>
                        </Link>
                    </CardFooter>
                </Card>
            </div>

            <!-- Active Subscription -->
            <div v-else class="space-y-6">
                <!-- Grace Period Alert -->
                <Alert
                    v-if="subscription.on_grace_period"
                    variant="destructive"
                >
                    <IconInfo />
                    <AlertTitle>{{
                        $t('Subscription Canceled')
                    }}</AlertTitle>
                    <AlertDescription>
                        {{
                            $t(
                                'Your subscription has been canceled but remains active until',
                            )
                        }}
                        {{ new Date(subscription.current_period_end).toLocaleDateString() }}.
                    </AlertDescription>
                </Alert>

                <!-- Current Plan Card -->
                <Card>
                    <CardHeader>
                        <div
                            class="flex items-center justify-between"
                        >
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <IconCreditCard class="h-5 w-5" />
                                    {{ $t('Current Plan') }}
                                </CardTitle>
                                <CardDescription>
                                    {{
                                        $t(
                                            'Your subscription details and billing information',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                            <Badge :variant="getStatusVariant(subscription.status)">
                                {{ subscription.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-muted-foreground text-sm">
                                    {{ $t('Plan') }}
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ subscription.plan }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">
                                    {{ $t('Price') }}
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ subscription.price }} /
                                    {{ subscription.interval }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">
                                    {{ $t('Status') }}
                                </p>
                                <p class="text-lg font-semibold capitalize">
                                    {{ subscription.status }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">
                                    {{
                                        subscription.is_canceled
                                            ? $t('Ends On')
                                            : $t('Next Billing Date')
                                    }}
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ new Date(subscription.current_period_end).toLocaleDateString() }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button
                            v-if="subscription.on_grace_period"
                            @click="resumeSubscription(subscription.id)"
                        >
                            {{ $t('Resume Subscription') }}
                        </Button>
                        <Button
                            v-else
                            variant="destructive"
                            @click="cancelSubscription(subscription.id)"
                        >
                            {{ $t('Cancel Subscription') }}
                        </Button>
                        <Link :href="route('billing.plans')">
                            <Button variant="outline">
                                {{ $t('View All Plans') }}
                            </Button>
                        </Link>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
