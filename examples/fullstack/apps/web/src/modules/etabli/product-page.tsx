import { useState } from "react";
import { ArrowLeft, Check, Truck, Zap } from "lucide-react";
import { toast } from "sonner";
import { money } from "@/lib/format";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";
import { useStore } from "./etabli-app";
import {
  buttonPrimary,
  focusRing,
  Price,
  ProductStage,
  QuantityStepper,
} from "./parts";

export function ProductPage({ id }: { id: string }) {
  const { shop, cart } = useStore();
  const [quantity, setQuantity] = useState(1);
  const product = shop.products.find((p) => p.id === id);

  if (!product) {
    return (
      <div className="py-20 text-center">
        <h1 className="font-etabli-display text-3xl font-bold">
          Ce produit n’existe plus
        </h1>
        <a
          href={href("/etabli")}
          className={cn("mt-4 inline-block underline", focusRing)}
        >
          Revenir à la boutique
        </a>
      </div>
    );
  }

  const inCart = cart.quantity(product.id);
  const related = [
    ...shop.products.filter(
      (p) => p.id !== product.id && p.category === product.category,
    ),
    ...shop.products.filter(
      (p) => p.id !== product.id && p.category !== product.category,
    ),
  ].slice(0, 4);

  return (
    <>
      <a
        href={href("/etabli")}
        className={cn(
          "inline-flex items-center gap-2 rounded-full text-sm text-ardoise-soft hover:text-ardoise",
          focusRing,
        )}
      >
        <ArrowLeft className="size-4" aria-hidden="true" />
        Boutique
      </a>
      <div className="mt-6 grid gap-10 md:grid-cols-2 lg:gap-16">
        <ProductStage
          product={product}
          size="lg"
          className="aspect-square md:sticky md:top-8"
        />
        <div>
          <p className="text-sm text-ardoise-soft">{product.category}</p>
          <h1 className="mt-2 font-etabli-display text-5xl leading-none font-extrabold tracking-tight">
            {product.name}
          </h1>
          <div className="mt-5">
            <Price product={product} member={shop.perks.memberPrices} large />
          </div>
          <p className="mt-6 max-w-prose text-lg leading-relaxed text-ardoise-soft">
            {product.description}
          </p>
          <ul className="mt-6 grid gap-2.5">
            {product.highlights.map((highlight) => (
              <li key={highlight} className="flex gap-3">
                <Check className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                {highlight}
              </li>
            ))}
          </ul>
          <div className="mt-8 flex flex-wrap items-center gap-3">
            <QuantityStepper
              value={quantity}
              onChange={(value) => setQuantity(Math.max(1, value))}
              label={product.name}
            />
            <button
              type="button"
              className={buttonPrimary}
              disabled={inCart + quantity > 10}
              onClick={() => {
                cart.add(product.id, quantity);
                toast.success(
                  quantity > 1
                    ? `${quantity} × ${product.name} ajoutés au panier.`
                    : `${product.name} ajouté au panier.`,
                );
              }}
            >
              Ajouter au panier
            </button>
          </div>
          {inCart > 0 && (
            <p className="mt-3 text-sm text-ardoise-soft">
              Déjà {inCart} dans ton{" "}
              <a
                href={href("/etabli/panier")}
                className={cn("underline", focusRing)}
              >
                panier
              </a>
              .
            </p>
          )}
          <dl className="mt-8 grid gap-4 rounded-2xl border border-ardoise/15 p-5 text-sm">
            <div className="flex gap-3">
              <Truck className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
              <div>
                <dt className="font-medium">Livraison standard, 3 à 5 jours</dt>
                <dd className="text-ardoise-soft">
                  {shop.perks.freeShipping
                    ? "Offerte avec ton adhésion."
                    : `${money(shop.shippingRates.standard)}, offerte avec le Club.`}
                </dd>
              </div>
            </div>
            <div className="flex gap-3">
              <Zap className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
              <div>
                <dt className="font-medium">Express, livré demain</dt>
                <dd className="text-ardoise-soft">
                  {shop.perks.expressCredit
                    ? "Inclus dans ton adhésion."
                    : `${money(shop.shippingRates.express)}, inclus deux fois par mois avec le Club.`}
                </dd>
              </div>
            </div>
          </dl>
        </div>
      </div>

      <section aria-labelledby="related" className="mt-24">
        <h2 id="related" className="font-etabli-display text-2xl font-bold">
          À poser à côté
        </h2>
        <ul className="mt-6 grid grid-cols-2 gap-5 lg:grid-cols-4">
          {related.map((item) => (
            <li key={item.id}>
              <a
                href={href(`/etabli/produit/${item.id}`)}
                className={cn("group block rounded-[1.75rem]", focusRing)}
              >
                <ProductStage product={item} className="aspect-square" />
                <h3 className="mt-3 font-etabli-display font-semibold">
                  {item.name}
                </h3>
              </a>
              <Price product={item} member={shop.perks.memberPrices} />
            </li>
          ))}
        </ul>
      </section>
    </>
  );
}
