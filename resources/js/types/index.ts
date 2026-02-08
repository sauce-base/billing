export interface PriceMetadata {
    badge?: string;
    label?: string;
    original_price?: string;
}

export interface ProductMetadata {
    badge?: string;
    tagline?: string;
    cta_label?: string;
    cta_url?: string;
    [key: string]: any;
}

export interface Price {
    id: number;
    amount: number;
    currency: string;
    interval: string | null;
    interval_count?: number;
    provider_price_id?: string;
    is_active?: boolean;
    metadata?: PriceMetadata;
}

export interface Product {
    id: number;
    name: string;
    slug?: string;
    description: string | null;
    features: string[];
    is_highlighted?: boolean;
    prices: Price[];
    metadata?: ProductMetadata;
}

export interface CheckoutSession {
    id: number;
    uuid: string;
    price: Price & { product: Product };
    status: string;
    expires_at: string | null;
}
