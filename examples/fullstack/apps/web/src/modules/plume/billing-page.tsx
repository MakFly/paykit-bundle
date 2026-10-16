import { Check, Loader2 } from "lucide-react";
import { date, money } from "@/lib/format";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { planPerks, usePlume, UsageMeter } from "./plume-app";

export function BillingPage() {
  const { api, account } = usePlume();
  const subscription = account.customer.subscriptions[0];
  const current = account.plans.find((p) => p.id === subscription?.planId);
  const scheduled = account.plans.find(
    (p) => p.id === subscription?.scheduledPlanId,
  );

  const choose = (planId: string, label: string) =>
    void api.run(`plan:${planId}`, "/subscribe", { planId }, label);

  return (
    <div className="h-full overflow-y-auto">
      <div className="mx-auto max-w-4xl px-5 py-10 sm:px-8">
        <h1 className="text-2xl font-semibold tracking-tight">
          Forfait et facturation
        </h1>

        <section
          aria-labelledby="usage"
          className="mt-8 grid gap-6 rounded-xl border bg-card p-6 sm:grid-cols-[1fr_16rem] sm:items-center"
        >
          <div>
            <h2 id="usage" className="text-sm text-muted-foreground">
              Forfait actuel
            </h2>
            <p className="mt-1 text-2xl font-semibold">
              {current?.name ?? "Aucun"}
              {current && current.amount > 0 && (
                <span className="ml-2 text-base font-normal text-muted-foreground">
                  {money(current.amount, current.currency, 0)} par mois
                </span>
              )}
            </p>
            <p className="mt-2 text-sm text-muted-foreground">
              Carte Visa se terminant par 4242 (fictive).
              {subscription?.currentPeriodEnd &&
                ` Prochaine échéance le ${date(subscription.currentPeriodEnd)}.`}
            </p>
          </div>
          <UsageMeter />
        </section>

        {scheduled && subscription && (
          <div
            role="status"
            className="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-muted/50 p-4"
          >
            <p>
              Passage à <strong>{scheduled.name}</strong> le{" "}
              {date(subscription.currentPeriodEnd)}. Ton forfait actuel reste
              actif jusque-là.
            </p>
            <Button
              variant="outline"
              disabled={api.pending !== null}
              onClick={() =>
                choose(subscription.planId, "Annulation du changement")
              }
            >
              Garder {current?.name}
            </Button>
          </div>
        )}

        <h2 className="mt-10 text-lg font-semibold">Changer de forfait</h2>
        <ul className="mt-4 grid gap-4 md:grid-cols-3">
          {account.plans.map((plan) => {
            const active = plan.id === subscription?.planId;
            const isScheduled = plan.id === subscription?.scheduledPlanId;
            const upgrade = plan.amount > (current?.amount ?? 0);
            return (
              <li
                key={plan.id}
                className={cn(
                  "flex flex-col rounded-xl border bg-card p-6",
                  active ? "border-primary ring-1 ring-primary" : "",
                )}
              >
                <h3 className="font-semibold">{plan.name}</h3>
                <p className="mt-2 text-3xl font-semibold tabular-nums">
                  {money(plan.amount, plan.currency, 0)}
                  <span className="ml-1 text-sm font-normal text-muted-foreground">
                    par mois
                  </span>
                </p>
                <ul className="mt-4 mb-6 grid gap-2 text-sm">
                  {planPerks(plan).map((perk) => (
                    <li key={perk} className="flex gap-2">
                      <Check
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                      />
                      {perk}
                    </li>
                  ))}
                </ul>
                <Button
                  variant={upgrade ? "default" : "outline"}
                  disabled={api.pending !== null || active || isScheduled}
                  onClick={() => choose(plan.id, `Forfait ${plan.name}`)}
                  className="mt-auto"
                >
                  {api.pending === `plan:${plan.id}` && (
                    <Loader2 className="animate-spin" aria-hidden="true" />
                  )}
                  {active
                    ? "Forfait actuel"
                    : isScheduled
                      ? "Prévu à l’échéance"
                      : upgrade
                        ? `Passer à ${plan.name}`
                        : `Revenir à ${plan.name}`}
                </Button>
                <p className="mt-2 min-h-8 text-center text-xs text-muted-foreground">
                  {!active &&
                    !isScheduled &&
                    (upgrade
                      ? "Immédiat, au prorata du mois en cours."
                      : "À la fin de la période en cours.")}
                </p>
              </li>
            );
          })}
        </ul>
      </div>
    </div>
  );
}
