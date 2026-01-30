<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardFooter from '@/components/ui/card/CardFooter.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

import IconCheck from '~icons/heroicons/check';
import IconSparkles from '~icons/heroicons/sparkles';

interface PlanPrice {
    id: number;
    amount: string;
    currency: string;
    billing_interval: string;
    stripe_price_id: string;
}

interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string;
    features: string[];
    prices: PlanPrice[];
}

const props = defineProps<{
    plans: Plan[];
    stripe_publishable_key: string;
}>();

const title = 'Subscription Plans';
const selectedInterval = ref<'month' | 'year'>('month');

const getPriceForInterval = (plan: Plan, interval: string) => {
    return plan.prices.find((p) => p.billing_interval === interval);
};

const formatPrice = (amount: string) => {
    return `$${parseFloat(amount).toFixed(2)}`;
};

const subscribe = async (planPrice: PlanPrice) => {
    // For MVP, we'll redirect to Stripe Checkout
    // In Phase 2, we can implement custom checkout with Stripe Elements
    alert(
        'Stripe Checkout integration coming soon! For now, use the API directly.',
    );

    // TODO: Implement Stripe Checkout redirect
    // const stripe = await loadStripe(props.stripe_publishable_key);
    // Create checkout session via API and redirect
};
</script>

<template>
    <AppLayout :title="title" :breadcrumbs="[{ title: 'Billing', route: 'billing.index' }, { title: title }]">
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <!-- Header Section -->
            <div class="text-center">
                <h1 class="text-3xl font-bold tracking-tight">
                    {{ title }}
                </h1>
                <p class="text-muted-foreground mt-2 text-lg">
                    {{ $t('Choose the perfect plan for your needs') }}
                </p>
            </div>

            <!-- Billing Interval Toggle -->
            <div class="flex justify-center gap-2">
                <Button
                    :variant="selectedInterval === 'month' ? 'default' : 'outline'"
                    @click="selectedInterval = 'month'"
                >
                    {{ $t('Monthly') }}
                </Button>
                <Button
                    :variant="selectedInterval === 'year' ? 'default' : 'outline'"
                    @click="selectedInterval = 'year'"
                >
                    {{ $t('Yearly') }}
                    <Badge class="ml-2" variant="secondary">
                        {{ $t('Save 15%') }}
                    </Badge>
                </Button>
            </div>

            <!-- Plans Grid -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="plan in plans"
                    :key="plan.id"
                    :class="[
                        'relative flex flex-col',
                        plan.slug === 'pro' ? 'border-primary shadow-lg' : '',
                    ]"
                >
                    <!-- Recommended Badge -->
                    <div
                        v-if="plan.slug === 'pro'"
                        class="bg-primary text-primary-foreground absolute -top-4 left-1/2 -translate-x-1/2 rounded-full px-4 py-1 text-sm font-semibold"
                    >
                        <IconSparkles class="mr-1 inline size-4" />
                        {{ $t('Recommended') }}
                    </div>

                    <CardHeader>
                        <CardTitle class="text-2xl">{{ plan.name }}</CardTitle>
                        <CardDescription>{{ plan.description }}</CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1">
                        <!-- Price -->
                        <div class="mb-6">
                            <div
                                v-if="getPriceForInterval(plan, selectedInterval)"
                                class="flex items-baseline gap-1"
                            >
                                <span class="text-4xl font-bold">
                                    {{ formatPrice(getPriceForInterval(plan, selectedInterval)!.amount) }}
                                </span>
                                <span class="text-muted-foreground text-sm">
                                    / {{ selectedInterval }}
                                </span>
                            </div>
                            <div v-else class="text-muted-foreground">
                                {{ $t('Contact us') }}
                            </div>
                        </div>

                        <!-- Features List -->
                        <ul class="space-y-3">
                            <li
                                v-for="feature in plan.features"
                                :key="feature"
                                class="flex items-start gap-2"
                            >
                                <IconCheck
                                    class="text-primary mt-0.5 size-5 flex-shrink-0"
                                />
                                <span class="text-sm">{{ feature }}</span>
                            </li>
                        </ul>
                    </CardContent>

                    <CardFooter>
                        <Button
                            v-if="getPriceForInterval(plan, selectedInterval)"
                            class="w-full"
                            :variant="plan.slug === 'pro' ? 'default' : 'outline'"
                            @click="subscribe(getPriceForInterval(plan, selectedInterval)!)"
                        >
                            {{ $t('Subscribe') }}
                        </Button>
                        <Button
                            v-else
                            class="w-full"
                            variant="outline"
                            as-child
                        >
                            <a href="mailto:support@saucebase.dev">
                                {{ $t('Contact Sales') }}
                            </a>
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <!-- Back Link -->
            <div class="text-center">
                <Link :href="route('billing.index')">
                    <Button variant="ghost">
                        {{ $t('Back to Billing Dashboard') }}
                    </Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
