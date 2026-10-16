import { Check, Loader2 } from "lucide-react";
import type { Plan } from "@/lib/api";
import { date, money } from "@/lib/format";
import { cn } from "@/lib/utils";
import { useStore } from "./etabli-app";
import { buttonGhost, buttonPrimary, Tape } from "./parts";

const pitch: Record<string, string> = {
  club_none: "Tu paies la livraison à chaque commande.",
  club: "Pour commander souvent sans compter les frais de port.",
  club_plus: "Pour équiper tout le bureau au meilleur prix.",
};

function perks(plan: Plan, standardRate: number) {
  const list = [
    "free_shipping" in plan.includes
      ? "Livraison standard offerte"
      : `Livraison standard à ${money(standardRate)}`,
  ];
  if ("express_shipping" in plan.includes)
    list.push(
      plan.includes.express_shipping === null
        ? "Express illimitée"
        : `${plan.includes.express_shipping} livraisons express par mois`,
    );
  if ("member_prices" in plan.includes)
    list.push("Prix membres, environ 10 % de moins");
  return list;
}

export function ClubPage() {
  const { api, shop } = useStore();
  const subscription = shop.customer.subscriptions[0];
  const express = shop.customer.entitlements.express_shipping;
  const scheduled = shop.plans.find(
    (p) => p.id === subscription?.scheduledPlanId,
  );

  return (
    <>
      <div className="max-w-2xl">
        <Tape>Club Établi</Tape>
        <h1 className="mt-4 font-etabli-display text-5xl leading-none font-extrabold tracking-tight text-balance">
          Une adhésion, et la livraison ne compte plus.
        </h1>
        <p className="mt-5 text-lg text-ardoise-soft">
          Sans engagement. Une montée en gamme s’applique tout de suite, une
          baisse à la fin du mois en cours.
        </p>
      </div>

      {scheduled && (
        <div
          role="status"
          className="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-ardoise/20 bg-craie p-5"
        >
          <p>
            Passage à <strong>{scheduled.name}</strong> le{" "}
            {date(subscription?.currentPeriodEnd)}. Tes avantages restent actifs
            jusque-là.
          </p>
          <button
            type="button"
            className={buttonGhost}
            disabled={api.pending !== null}
            onClick={() =>
              void api.run(
                `plan:${subscription!.planId}`,
                "/subscribe",
                { planId: subscription!.planId },
                "Annulation du changement",
              )
            }
          >
            Garder mon adhésion
          </button>
        </div>
      )}

      <ul className="mt-12 grid gap-5 md:grid-cols-3">
        {shop.plans.map((plan) => {
          const active = subscription?.planId === plan.id;
          const isScheduled = subscription?.scheduledPlanId === plan.id;
          const highlight = plan.id === "club";
          return (
            <li
              key={plan.id}
              className={cn(
                "flex flex-col rounded-[1.75rem] p-7",
                highlight ? "bg-ardoise text-craie" : "bg-craie",
              )}
            >
              <div className="flex h-8 items-center justify-between gap-2">
                <h2 className="font-etabli-display text-2xl font-bold">
                  {plan.name}
                </h2>
                {active && <Tape className="text-xs">Ton adhésion</Tape>}
              </div>
              <p
                className={cn(
                  "mt-2 min-h-10 text-sm",
                  highlight ? "text-craie/70" : "text-ardoise-soft",
                )}
              >
                {pitch[plan.id]}
              </p>
              <p className="mt-6 font-etabli-display text-4xl font-bold tabular-nums">
                {plan.amount ? money(plan.amount) : "Gratuit"}
                {plan.amount > 0 && (
                  <span className="ml-1 text-base font-medium opacity-70">
                    par mois
                  </span>
                )}
              </p>
              <ul className="mt-6 mb-8 grid gap-3 text-sm">
                {perks(plan, shop.shippingRates.standard).map((perk) => (
                  <li key={perk} className="flex gap-2.5">
                    <Check
                      className={cn(
                        "size-4 shrink-0",
                        highlight && "text-ruban",
                      )}
                      aria-hidden="true"
                    />
                    {perk}
                  </li>
                ))}
              </ul>
              <button
                type="button"
                disabled={api.pending !== null || active || isScheduled}
                onClick={() =>
                  void api.run(
                    `plan:${plan.id}`,
                    "/subscribe",
                    { planId: plan.id },
                    `Adhésion ${plan.name}`,
                  )
                }
                className={cn(
                  "mt-auto",
                  highlight
                    ? "inline-flex items-center justify-center gap-2 rounded-full bg-ruban px-6 py-3 text-sm font-semibold text-ardoise outline-none hover:bg-ruban/90 focus-visible:ring-2 focus-visible:ring-ruban disabled:opacity-50"
                    : plan.amount === 0
                      ? buttonGhost
                      : buttonPrimary,
                )}
              >
                {api.pending === `plan:${plan.id}` && (
                  <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                )}
                {active
                  ? "Adhésion actuelle"
                  : isScheduled
                    ? "Prévu à la fin du mois"
                    : plan.amount === 0
                      ? "Revenir sans adhésion"
                      : `Choisir ${plan.name}`}
              </button>
            </li>
          );
        })}
      </ul>

      {express && (
        <p className="mt-8 text-ardoise-soft">
          {express.unlimited
            ? "Livraisons express illimitées ce mois-ci."
            : `Livraisons express restantes ce mois-ci : ${express.remaining} sur ${express.limit}, renouvelées le ${date(express.resetAt)}.`}
        </p>
      )}
    </>
  );
}
