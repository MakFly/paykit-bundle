import { useState } from "react";
import { Check, Plus } from "lucide-react";
import { toast } from "sonner";
import { money } from "@/lib/format";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";
import { useStore } from "./etabli-app";
import {
  buttonGhost,
  buttonPrimary,
  focusRing,
  Price,
  ProductStage,
  Tape,
} from "./parts";
import type { Product } from "./types";

const FEATURED = "lampe-led";

export function HomePage() {
  const { shop, cart } = useStore();
  const [category, setCategory] = useState<string | null>(null);
  const member = shop.perks.memberPrices;
  const featured =
    shop.products.find((p) => p.id === FEATURED) ?? shop.products[0];
  const categories = [...new Set(shop.products.map((p) => p.category))];
  const products = shop.products.filter(
    (p) => !category || p.category === category,
  );
  const club = shop.plans.find((p) => p.id === "club");
  const clubPlus = shop.plans.find((p) => p.id === "club_plus");
  const plan = shop.customer.subscriptions[0]?.planId;

  const add = (product: Product) => {
    cart.add(product.id);
    toast.success(`${product.name} ajouté au panier.`);
  };

  return (
    <>
      <section className="grid items-center gap-8 md:grid-cols-12 md:gap-10">
        <div className="md:col-span-5">
          <p className="text-sm font-medium text-ardoise-soft">
            Nouveau dans l’atelier, rayon {featured.category.toLowerCase()}
          </p>
          <h1 className="mt-3 font-etabli-display text-5xl leading-[0.95] font-extrabold tracking-tight text-balance lg:text-7xl">
            {featured.name}
          </h1>
          <p className="mt-5 max-w-md text-lg leading-relaxed text-ardoise-soft">
            {featured.description}
          </p>
          <div className="mt-6">
            <Price product={featured} member={member} large />
          </div>
          <div className="mt-7 flex flex-wrap gap-3">
            <button
              type="button"
              className={buttonPrimary}
              onClick={() => add(featured)}
            >
              <Plus aria-hidden="true" />
              Ajouter au panier
            </button>
            <a
              href={href(`/etabli/produit/${featured.id}`)}
              className={buttonGhost}
            >
              Voir la fiche
            </a>
          </div>
        </div>
        <a
          href={href(`/etabli/produit/${featured.id}`)}
          aria-label={`${featured.name}, voir la fiche`}
          className={cn("rounded-[1.75rem] md:col-span-7", focusRing)}
        >
          <ProductStage product={featured} size="lg" className="aspect-[5/4]" />
        </a>
      </section>

      <section aria-labelledby="catalogue" className="mt-24">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <h2
            id="catalogue"
            className="font-etabli-display text-3xl font-bold tracking-tight"
          >
            Tout le catalogue
          </h2>
          <div
            role="group"
            aria-label="Filtrer par rayon"
            className="flex flex-wrap gap-2"
          >
            {[null, ...categories].map((name) => (
              <button
                key={name ?? "all"}
                type="button"
                aria-pressed={category === name}
                onClick={() => setCategory(name)}
                className={cn(
                  "rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors",
                  category === name
                    ? "border-ardoise bg-ardoise text-craie"
                    : "border-ardoise/20 hover:border-ardoise/60",
                  focusRing,
                )}
              >
                {name ?? "Tout"}
              </button>
            ))}
          </div>
        </div>
        <ul className="mt-8 grid grid-cols-2 gap-x-5 gap-y-10 lg:grid-cols-4">
          {products.map((product) => {
            const inCart = cart.quantity(product.id);
            return (
              <li key={product.id} className="flex flex-col">
                <a
                  href={href(`/etabli/produit/${product.id}`)}
                  className={cn("group rounded-[1.75rem]", focusRing)}
                >
                  <ProductStage
                    product={product}
                    className="aspect-square transition-transform duration-300 group-hover:scale-[0.98]"
                  />
                  <p className="mt-4 text-sm text-ardoise-soft">
                    {product.category}
                  </p>
                  <h3 className="font-etabli-display text-lg font-semibold">
                    {product.name}
                  </h3>
                </a>
                <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                  <Price product={product} member={member} />
                  <button
                    type="button"
                    onClick={() => add(product)}
                    disabled={inCart >= 10}
                    aria-label={`Ajouter ${product.name} au panier`}
                    className={cn(
                      "grid size-9 place-items-center rounded-full border border-ardoise/25 hover:bg-ardoise hover:text-craie disabled:opacity-40",
                      inCart > 0 && "border-ardoise bg-ardoise text-craie",
                      focusRing,
                    )}
                  >
                    {inCart > 0 ? (
                      <span className="text-xs font-bold tabular-nums">
                        {inCart}
                      </span>
                    ) : (
                      <Plus className="size-4" aria-hidden="true" />
                    )}
                  </button>
                </div>
              </li>
            );
          })}
        </ul>
      </section>

      <section
        aria-labelledby="club"
        className="mt-24 grid gap-10 rounded-[1.75rem] bg-ardoise px-6 py-10 text-craie sm:px-10 md:grid-cols-2 md:py-14"
      >
        <div>
          <Tape>Club Établi</Tape>
          <h2
            id="club"
            className="mt-4 font-etabli-display text-4xl leading-tight font-bold tracking-tight text-balance"
          >
            La livraison offerte, toute l’année.
          </h2>
          <ul className="mt-6 grid gap-3 text-craie/80">
            {[
              "Livraison standard offerte sur chaque commande",
              "Deux livraisons express par mois, illimitées avec Club+",
              "Prix membres sur tout le catalogue avec Club+",
            ].map((perk) => (
              <li key={perk} className="flex gap-3">
                <Check
                  className="mt-0.5 size-5 shrink-0 text-ruban"
                  aria-hidden="true"
                />
                {perk}
              </li>
            ))}
          </ul>
        </div>
        <div className="flex flex-col justify-end gap-6 md:items-end md:text-right">
          {plan && plan !== "club_none" ? (
            <p className="text-lg">
              Tu es membre{" "}
              {shop.plans.find((p) => p.id === plan)?.name ?? "du Club"}.
            </p>
          ) : (
            <p className="font-etabli-display text-2xl">
              {club && `Club ${money(club.amount)} par mois`}
              <br />
              <span className="text-craie/70">
                {clubPlus && `Club+ ${money(clubPlus.amount)} par mois`}
              </span>
            </p>
          )}
          <a
            href={href("/etabli/club")}
            className="inline-flex items-center justify-center rounded-full bg-ruban px-6 py-3 text-sm font-semibold text-ardoise outline-none hover:bg-ruban/90 focus-visible:ring-2 focus-visible:ring-ruban focus-visible:ring-offset-2 focus-visible:ring-offset-ardoise"
          >
            {plan && plan !== "club_none"
              ? "Gérer mon adhésion"
              : "Découvrir le Club"}
          </a>
        </div>
      </section>
    </>
  );
}
