import { useEffect, useState } from "react";
import { Loader2, Truck, X, Zap } from "lucide-react";
import { request } from "@/lib/api";
import { money } from "@/lib/format";
import { href, navigate } from "@/lib/router";
import { cn } from "@/lib/utils";
import { useStore } from "./etabli-app";
import {
  buttonGhost,
  buttonPrimary,
  focusRing,
  ProductStage,
  QuantityStepper,
} from "./parts";
import type { Order, Quote, Shipping, ShopSnapshot } from "./types";

export function CartPage() {
  const { api, shop, cart } = useStore();
  const [shipping, setShipping] = useState<Shipping>("standard");
  const [quote, setQuote] = useState<Quote | null>(null);
  const [quoteError, setQuoteError] = useState<string | null>(null);
  const { lines } = cart;
  const express = shop.customer.entitlements.express_shipping;
  // The API prices the cart: client-side prices are only shown, never trusted.
  const pricingKey = JSON.stringify([lines, shipping, shop.perks]);

  useEffect(() => {
    if (lines.length === 0) {
      setQuote(null);
      return;
    }
    const controller = new AbortController();
    request<ShopSnapshot, Quote>(
      "/api/shop/quote",
      { items: lines, shipping },
      controller.signal,
    )
      .then(({ data }) => {
        setQuote((data.result as Quote | undefined) ?? null);
        setQuoteError(null);
      })
      .catch((cause: unknown) => {
        if (!controller.signal.aborted)
          setQuoteError(
            cause instanceof Error ? cause.message : "Total indisponible.",
          );
      });
    return () => controller.abort();
    // pricingKey covers lines, shipping and perks.
  }, [pricingKey]);

  async function pay() {
    const response = await api.run(
      "checkout",
      "/checkout",
      { items: lines, shipping },
      "Commande",
      { success: () => "Commande confirmée." },
    );
    const order = (response?.data.result as { order?: Order } | undefined)
      ?.order;
    if (response?.status === 201 && order) {
      cart.clear();
      navigate(`/etabli/commande/${order.id}`);
    }
  }

  if (lines.length === 0) {
    return (
      <div className="mx-auto max-w-md py-16 text-center">
        <h1 className="font-etabli-display text-4xl font-bold tracking-tight">
          Ton panier est vide
        </h1>
        <p className="mt-3 text-ardoise-soft">
          Ajoute un objet depuis la boutique, il t’attendra ici.
        </p>
        <a href={href("/etabli")} className={cn(buttonPrimary, "mt-8")}>
          Voir la boutique
        </a>
      </div>
    );
  }

  const options: {
    mode: Shipping;
    icon: typeof Truck;
    title: string;
    detail: string;
    free: boolean;
  }[] = [
    {
      mode: "standard",
      icon: Truck,
      title: "Standard",
      detail: "3 à 5 jours ouvrés",
      free: shop.perks.freeShipping,
    },
    {
      mode: "express",
      icon: Zap,
      title: "Express",
      detail: express
        ? express.unlimited
          ? "Livré demain, inclus sans limite"
          : `Livré demain, ${express.remaining} sur ${express.limit} inclus ce mois-ci`
        : "Livré demain",
      free: shop.perks.expressCredit,
    },
  ];

  return (
    <>
      <h1 className="font-etabli-display text-5xl font-extrabold tracking-tight">
        Panier
      </h1>
      <div className="mt-10 grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <ul className="divide-y divide-ardoise/10 border-y border-ardoise/10">
          {lines.map((line) => {
            const product = shop.products.find((p) => p.id === line.productId)!;
            const unit = shop.perks.memberPrices
              ? product.memberPrice
              : product.price;
            return (
              <li key={line.productId} className="flex gap-4 py-5 sm:gap-6">
                <a
                  href={href(`/etabli/produit/${product.id}`)}
                  className={cn("shrink-0 rounded-xl", focusRing)}
                  aria-label={product.name}
                >
                  <ProductStage
                    product={product}
                    size="sm"
                    className="size-20 sm:size-24"
                  />
                </a>
                <div className="flex min-w-0 flex-1 flex-col justify-between gap-3 sm:flex-row sm:items-center">
                  <div className="min-w-0">
                    <h2 className="font-etabli-display text-lg font-semibold">
                      {product.name}
                    </h2>
                    <p className="text-sm text-ardoise-soft tabular-nums">
                      {money(unit)} l’unité
                    </p>
                  </div>
                  <div className="flex items-center gap-4">
                    <QuantityStepper
                      value={line.quantity}
                      onChange={(value) => cart.set(product.id, value)}
                      label={product.name}
                    />
                    <p className="w-20 text-right font-semibold tabular-nums">
                      {money(unit * line.quantity)}
                    </p>
                    <button
                      type="button"
                      onClick={() => cart.set(product.id, 0)}
                      aria-label={`Retirer ${product.name} du panier`}
                      className={cn(
                        "grid size-8 place-items-center rounded-full text-ardoise-soft hover:bg-ardoise/10 hover:text-ardoise",
                        focusRing,
                      )}
                    >
                      <X className="size-4" />
                    </button>
                  </div>
                </div>
              </li>
            );
          })}
        </ul>

        <aside
          aria-labelledby="summary"
          className="rounded-[1.75rem] bg-craie p-6 lg:sticky lg:top-8"
        >
          <h2 id="summary" className="font-etabli-display text-xl font-bold">
            Livraison
          </h2>
          <fieldset className="mt-4 grid gap-2">
            <legend className="sr-only">Mode de livraison</legend>
            {options.map(({ mode, icon: Icon, title, detail, free }) => (
              <label
                key={mode}
                className={cn(
                  "flex cursor-pointer items-center gap-3 rounded-2xl border p-4 transition-colors has-focus-visible:ring-2 has-focus-visible:ring-ardoise",
                  shipping === mode
                    ? "border-ardoise bg-white"
                    : "border-ardoise/15 hover:border-ardoise/40",
                )}
              >
                <input
                  type="radio"
                  name="shipping"
                  value={mode}
                  checked={shipping === mode}
                  onChange={() => setShipping(mode)}
                  className="sr-only"
                />
                <Icon className="size-5 shrink-0" aria-hidden="true" />
                <span className="flex-1 text-sm">
                  <span className="block font-semibold">{title}</span>
                  <span className="text-ardoise-soft">{detail}</span>
                </span>
                <span className="text-sm font-semibold tabular-nums">
                  {free ? "Offerte" : money(shop.shippingRates[mode])}
                </span>
              </label>
            ))}
          </fieldset>

          {quoteError ? (
            <p className="mt-6 text-sm text-destructive">{quoteError}</p>
          ) : (
            <dl
              aria-busy={!quote}
              className={cn(
                "mt-6 grid grid-cols-[1fr_auto] gap-y-2 text-sm tabular-nums [&_dd]:text-right",
                !quote && "opacity-50",
              )}
            >
              <dt className="text-ardoise-soft">Sous-total</dt>
              <dd>{money(quote?.subtotal ?? 0)}</dd>
              {(quote?.discount ?? 0) > 0 && (
                <>
                  <dt className="text-ardoise-soft">Prix Club</dt>
                  <dd>−{money(quote!.discount)}</dd>
                </>
              )}
              <dt className="text-ardoise-soft">Livraison</dt>
              <dd>
                {quote?.shippingCost === 0
                  ? "Offerte"
                  : money(quote?.shippingCost ?? 0)}
              </dd>
              <dt className="mt-3 border-t border-ardoise/10 pt-3 font-etabli-display text-lg font-bold">
                Total
              </dt>
              <dd className="mt-3 border-t border-ardoise/10 pt-3 font-etabli-display text-lg font-bold">
                {money(quote?.total ?? 0)}
              </dd>
            </dl>
          )}
          <button
            type="button"
            className={cn(buttonPrimary, "mt-6 w-full")}
            disabled={api.pending !== null || !quote}
            onClick={() => void pay()}
          >
            {api.pending === "checkout" && (
              <Loader2 className="animate-spin" aria-hidden="true" />
            )}
            {quote ? `Payer ${money(quote.total)}` : "Payer"}
          </button>
          <p className="mt-3 text-center text-xs text-ardoise-soft">
            Paiement simulé, aucune carte n’est débitée.
          </p>
          {!shop.perks.freeShipping && (
            <a
              href={href("/etabli/club")}
              className={cn(buttonGhost, "mt-5 w-full")}
            >
              Livraison offerte avec le Club
            </a>
          )}
        </aside>
      </div>
    </>
  );
}
