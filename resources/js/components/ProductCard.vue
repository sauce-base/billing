<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import type { Product } from '@modules/Billing/resources/js/types';
import { getIntervalDisplay } from '../utils/intervals';

const props = defineProps<{
    product: Product;
}>();

function formatPrice(amount: number | string): string {
    const cents = typeof amount === 'string' ? parseFloat(amount) : amount;
    return `$${Math.round(cents / 100)}`;
}

const priceKey = computed(() => props.product.prices[0]?.amount);
const isAnimating = ref(false);

watch(priceKey, () => {
    isAnimating.value = true;
    setTimeout(() => {
        isAnimating.value = false;
    }, 150);
});
</script>

<template>
    <div
        class="relative flex h-full flex-col rounded-3xl p-8 shadow-lg"
        :class="
            product.metadata?.badge || product.is_highlighted
                ? 'ring-primary bg-white/70 shadow-lg ring-3 dark:bg-gray-900/70 scale-[1.05]'
                : 'bg-white/70  dark:bg-gray-900/60 dark:ring-white/10'
        "
    >
        <span
            v-if="product.metadata?.badge || product.is_highlighted"
            class="bg-primary absolute left-1/2 -translate-x-1/2 -translate-y-2/1 rounded-md px-3 py-1 text-xs font-semibold text-white shadow-2xl"
        >
            {{ product.metadata?.badge || $t('Most popular') }}
        </span>

        <!-- Plan Name with Badge -->
        <div class="flex items-center justify-between gap-x-4">
            <h3
                class="text-2xl font-semibold"
                :class="
                    product.metadata?.badge || product.is_highlighted
                        ? 'text-secondary dark:text-secondary-light'
                        : 'text-gray-900 dark:text-white'
                "
            >
                {{ product.name }}
            </h3>
        </div>

        <!-- Description -->
        <p
            v-if="product.description"
            class="mt-2 text-sm text-gray-600 dark:text-gray-300"
            v-html="product.description"
        ></p>

        <!-- Price -->
        <div class="mt-2">
            <!-- Original price + discount badge -->
            <div
                v-if="
                    product.prices[0]?.metadata?.original_price ||
                    product.prices[0]?.metadata?.badge
                "
                class="mb-1 flex items-center gap-2"
            >
                <span
                    v-if="product.prices[0]?.metadata?.original_price"
                    class="text-2xl text-gray-400 line-through dark:text-gray-600"
                >
                    {{ formatPrice(product.prices[0].metadata.original_price) }}
                </span>
                <span
                    v-if="product.prices[0]?.metadata?.badge"
                    class="text-sm font-medium text-green-600 dark:text-green-400"
                >
                    {{ product.prices[0].metadata.badge }}
                </span>
            </div>
            <div v-else>&nbsp;</div>

            <!-- Current price -->
            <div
                class="flex items-baseline gap-x-1 transition-transform duration-150"
                :class="{ 'scale-105': isAnimating }"
            >
                <template v-if="product.prices.length > 0">
                    <span
                        class="text-5xl font-semibold tracking-tight text-gray-900 dark:text-white"
                    >
                        {{ formatPrice(product.prices[0].amount) }}
                    </span>
                    <span class="text-base text-gray-500 dark:text-gray-400">
                        {{ getIntervalDisplay(product.prices[0].interval) }}
                    </span>
                </template>
                <span
                    v-else
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    {{ $t('Contact us') }}
                </span>
            </div>
        </div>

        <!-- Tagline from metadata -->
        <p
            v-if="product.metadata?.tagline"
            class="mt-2 text-sm text-gray-500 italic dark:text-gray-400"
        >
            {{ product.metadata.tagline }}
        </p>

        <!-- CTA Button -->
        <a
            v-if="product.metadata?.cta_url"
            :href="product.metadata.cta_url"
            class="mt-8 block w-full rounded-full px-4 py-3 text-center font-semibold shadow-2xl transition-all duration-200 hover:scale-105 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2"
            :class="
                product.metadata?.badge || product.is_highlighted
                    ? 'bg-primary hover:bg-primary/90 focus-visible:outline-primary text-white'
                    : 'text-gray-900 ring-1 ring-gray-200 ring-inset hover:ring-gray-300 dark:bg-white/10 dark:text-white dark:ring-white/10 dark:hover:bg-white/20'
            "
        >
            {{ product.metadata?.cta_label || $t('Get started') }}
        </a>
        <button
            v-else
            class="mt-8 w-full rounded-full px-4 py-3 font-semibold shadow-lg transition-all duration-200 hover:scale-105 focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2"
            :class="
                product.metadata?.badge || product.is_highlighted
                    ? 'bg-primary hover:bg-primary/90 focus-visible:outline-primary text-white'
                    : 'text-gray-900 ring-1 ring-gray-200 ring-inset hover:ring-gray-300 dark:bg-white/10 dark:text-white dark:ring-white/10 dark:hover:bg-white/20'
            "
        >
            {{ $t('Get started') }}
        </button>

        <!-- After CTA text from metadata -->
        <div class="text-center text-gray-500 dark:text-gray-400/90 mt-2 text-sm" v-if="product.metadata?.after_cta">
            {{ $t(product.metadata.after_cta) }}
        </div>

        <!-- Features -->
        <ul
            v-if="product.features?.length"
            class="mt-6 flex-1 space-y-1 text-sm text-gray-600 dark:text-gray-300"
        >
            <li
                v-for="(feature, index) in product.features"
                :key="index"
                class="flex items-start gap-3"
            >
                <svg
                    class="text-primary mt-0.5 h-5 w-5 shrink-0"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span>{{ feature }}</span>
            </li>
        </ul>
    </div>
</template>
