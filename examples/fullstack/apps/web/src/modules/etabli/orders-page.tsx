import { Check, Zap } from "lucide-react";
import { money } from "@/lib/format";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";
import { useStore } from "./etabli-app";
import { buttonPrimary, focusRing } from "./parts";
import type { Order } from "./types";

const when = (value: string) =>
  new Intl.DateTimeFormat("fr-FR", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(new Date(value));
const reference = (order: Order) => order.id.slice(-6).toUpperCase();
const delivery = (order: Order) =>
  order.shipping === "express"
    ? order.expressCredit
      ? "Express, incluse dans le Club"
      : `Express, ${money(order.shippingCost)}`
    : order.shippingCost === 0
      ? "Standard, offerte"
      : `Standard, ${money(order.shippingCost)}`;

export function OrderPage({ id }: { id: string }) {
  const { shop } = useStore();
  const order = shop.orders.find((o) => o.id === id);
  if (!order) return <OrdersPage />;
  return (
    <div className="mx-auto max-w-2xl">
      <span className="grid size-12 place-items-center rounded-full bg-ruban">
        <Check className="size-6" aria-hidden="true" />
      </span>
      <h1 className="mt-6 font-etabli-display text-5xl font-extrabold tracking-tight">
        Commande confirmée
      </h1>
      <p className="mt-3 text-lg text-ardoise-soft">
        Commande {reference(order)} du {when(order.createdAt)}.{" "}
        {order.shipping === "express"
          ? "Elle part aujourd’hui en express."
          : "Elle arrive sous 3 à 5 jours."}
      </p>
      <OrderLines order={order} />
      <div className="mt-8 flex flex-wrap gap-3">
        <a href={href("/etabli")} className={buttonPrimary}>
          Continuer mes achats
        </a>
      </div>
    </div>
  );
}

function OrderLines({ order }: { order: Order }) {
  return (
    <dl className="mt-8 grid grid-cols-[1fr_auto] gap-y-2 rounded-[1.75rem] bg-craie p-6 tabular-nums [&_dd]:text-right">
      {order.items.map((item) => (
        <div key={item.productId} className="contents">
          <dt>
            {item.quantity} × {item.name}
          </dt>
          <dd>{money(item.total)}</dd>
        </div>
      ))}
      <dt className="text-ardoise-soft">{delivery(order)}</dt>
      <dd className="text-ardoise-soft">
        {order.shippingCost ? money(order.shippingCost) : "Offerte"}
      </dd>
      <dt className="mt-2 border-t border-ardoise/10 pt-3 font-etabli-display font-bold">
        Total payé
      </dt>
      <dd className="mt-2 border-t border-ardoise/10 pt-3 font-etabli-display font-bold">
        {money(order.total)}
      </dd>
    </dl>
  );
}

export function OrdersPage() {
  const { shop } = useStore();
  return (
    <>
      <h1 className="font-etabli-display text-5xl font-extrabold tracking-tight">
        Commandes
      </h1>
      {shop.orders.length === 0 ? (
        <div className="mt-10 max-w-md">
          <p className="text-lg text-ardoise-soft">
            Tes commandes apparaîtront ici après ton premier achat.
          </p>
          <a href={href("/etabli")} className={cn(buttonPrimary, "mt-6")}>
            Voir la boutique
          </a>
        </div>
      ) : (
        <ul className="mt-10 divide-y divide-ardoise/10 border-y border-ardoise/10">
          {shop.orders.map((order) => (
            <li key={order.id}>
              <a
                href={href(`/etabli/commande/${order.id}`)}
                className={cn(
                  "flex flex-wrap items-center justify-between gap-4 py-5 hover:bg-ardoise/[0.03]",
                  focusRing,
                )}
              >
                <div className="min-w-0">
                  <p className="font-etabli-display font-semibold">
                    {order.items
                      .map((item) => `${item.quantity} × ${item.name}`)
                      .join(", ")}
                  </p>
                  <p className="text-sm text-ardoise-soft">
                    Commande {reference(order)}, {when(order.createdAt)}
                  </p>
                </div>
                <div className="flex items-center gap-4 text-sm">
                  <span className="flex items-center gap-1.5 text-ardoise-soft">
                    {order.shipping === "express" && (
                      <Zap className="size-4" aria-hidden="true" />
                    )}
                    {delivery(order)}
                  </span>
                  <span className="font-etabli-display text-lg font-bold tabular-nums">
                    {money(order.total)}
                  </span>
                </div>
              </a>
            </li>
          ))}
        </ul>
      )}
      <p className="mt-6 text-sm text-ardoise-soft">
        Les cinq dernières commandes sont affichées.
      </p>
    </>
  );
}
