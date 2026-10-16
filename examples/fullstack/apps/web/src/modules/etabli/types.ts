import type { ModuleSnapshot } from "@/lib/api";

export interface Product {
  id: string;
  name: string;
  category: string;
  icon: string;
  tint: string;
  price: number;
  memberPrice: number;
  description: string;
  highlights: string[];
}

export type Shipping = "standard" | "express";

export interface Quote {
  items: {
    productId: string;
    name: string;
    quantity: number;
    unitPrice: number;
    total: number;
  }[];
  subtotal: number;
  discount: number;
  shipping: Shipping;
  shippingCost: number;
  expressCredit: boolean;
  total: number;
  currency: string;
}

export interface Order extends Quote {
  id: string;
  createdAt: string;
}

export interface ShopSnapshot extends ModuleSnapshot {
  products: Product[];
  perks: {
    freeShipping: boolean;
    memberPrices: boolean;
    expressCredit: boolean;
  };
  shippingRates: Record<Shipping, number>;
  orders: Order[];
}

export type CartLine = { productId: string; quantity: number };
