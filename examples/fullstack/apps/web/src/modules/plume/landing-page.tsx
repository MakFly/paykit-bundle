import type { CSSProperties } from "react";
import { Check, Feather, Loader2 } from "lucide-react";
import { money } from "@/lib/format";
import { href, navigate } from "@/lib/router";
import { cn } from "@/lib/utils";
import {
  planPerks,
  plumeFocus,
  plumePrimary,
  plumeSecondary,
  usePlume,
  Wordmark,
} from "./plume-app";

const steps = [
  {
    title: "Colle ton brouillon",
    text: "Un courriel, une annonce, un compte rendu : Plume prend jusqu’à 2000 caractères.",
  },
  {
    title: "Choisis le modèle",
    text: "Standard nettoie le texte. Expert le découpe en paragraphes et en tire une phrase de résumé.",
  },
  {
    title: "Relis et copie",
    text: "Plume garde tes mots et retire ce qui les alourdit. Chaque réécriture compte pour un message.",
  },
];

export function LandingPage() {
  const { api, account } = usePlume();
  const current = account.customer.subscriptions[0]?.planId;
  const currentPlan = account.plans.find((p) => p.id === current);

  async function choose(planId: string) {
    if (planId === current) return navigate("/plume/app");
    const response = await api.run(
      `plan:${planId}`,
      "/subscribe",
      { planId },
      `Forfait ${planId}`,
    );
    if (response && response.status < 400) navigate("/plume/app");
  }

  return (
    <>
      <header className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-5 sm:px-8">
        <a href={href("/plume")} className={cn("rounded", plumeFocus)}>
          <Wordmark />
        </a>
        <nav
          aria-label="Site"
          className="ml-auto flex items-center gap-2 sm:gap-4"
        >
          <a
            href="#tarifs"
            onClick={(event) => {
              event.preventDefault();
              document
                .getElementById("tarifs")
                ?.scrollIntoView({ behavior: "smooth" });
            }}
            className={cn(
              "rounded px-2 text-sm font-medium text-nuit/70 hover:text-nuit",
              plumeFocus,
            )}
          >
            Tarifs
          </a>
          <a href={href("/plume/app")} className={plumePrimary}>
            Ouvrir Plume
          </a>
        </nav>
      </header>

      <main>
        <section className="mx-auto grid max-w-6xl items-center gap-12 px-5 pt-12 pb-20 sm:px-8 lg:grid-cols-[1fr_1.1fr] lg:pt-20">
          <div>
            <h1 className="font-plume-serif text-5xl leading-[1.05] font-medium tracking-tight text-balance sm:text-6xl">
              Tes brouillons, en plus clair.
            </h1>
            <p className="mt-6 max-w-lg text-lg leading-relaxed text-nuit/70">
              Colle un texte : Plume retire les tics de langage, coupe les
              phrases trop longues et garde ta façon d’écrire.
            </p>
            <div className="mt-8 flex flex-wrap items-center gap-4">
              <a href={href("/plume/app")} className={plumePrimary}>
                Essayer gratuitement
              </a>
              <p className="text-sm text-nuit/60">
                10 réécritures offertes chaque mois, sans carte.
              </p>
            </div>
          </div>

          <figure
            aria-label="Exemple de réécriture"
            className="rounded-2xl border border-nuit/10 bg-brume p-3 shadow-[0_24px_60px_-30px] shadow-encre/40"
          >
            <div className="rounded-xl bg-white p-6">
              <p className="text-xs font-semibold text-nuit/50">
                Ton brouillon
              </p>
              <p className="mt-3 font-plume-serif text-xl leading-relaxed">
                <span
                  className="plume-filler"
                  style={{ "--delay": "0.6s" } as CSSProperties}
                >
                  En fait,
                </span>{" "}
                je pense que le projet est{" "}
                <span
                  className="plume-filler"
                  style={{ "--delay": "0.9s" } as CSSProperties}
                >
                  vraiment
                </span>{" "}
                prêt,{" "}
                <span
                  className="plume-filler"
                  style={{ "--delay": "1.2s" } as CSSProperties}
                >
                  du coup
                </span>{" "}
                on peut{" "}
                <span
                  className="plume-filler"
                  style={{ "--delay": "1.5s" } as CSSProperties}
                >
                  juste
                </span>{" "}
                lancer la bêta lundi.
              </p>
            </div>
            <div className="plume-result mt-3 rounded-xl bg-encre-pale p-6">
              <p className="flex items-center gap-2 text-xs font-semibold text-encre">
                <Feather className="size-3.5" aria-hidden="true" />
                Plume, modèle Expert
              </p>
              <p className="mt-3 font-plume-serif text-xl leading-relaxed">
                Je pense que le projet est prêt. On peut lancer la bêta lundi.
              </p>
            </div>
          </figure>
        </section>

        <section
          aria-labelledby="etapes"
          className="border-y border-nuit/10 bg-brume"
        >
          <div className="mx-auto max-w-6xl px-5 py-16 sm:px-8">
            <h2 id="etapes" className="font-plume-serif text-3xl font-medium">
              Trois gestes, pas de réglages
            </h2>
            <ol className="mt-10 grid gap-8 md:grid-cols-3">
              {steps.map((step, index) => (
                <li key={step.title}>
                  <span className="font-plume-serif text-4xl text-encre italic">
                    {index + 1}.
                  </span>
                  <h3 className="mt-3 font-semibold">{step.title}</h3>
                  <p className="mt-2 leading-relaxed text-nuit/70">
                    {step.text}
                  </p>
                </li>
              ))}
            </ol>
          </div>
        </section>

        <section
          id="tarifs"
          aria-labelledby="tarifs-titre"
          className="mx-auto max-w-6xl scroll-mt-8 px-5 py-20 sm:px-8"
        >
          <h2
            id="tarifs-titre"
            className="font-plume-serif text-4xl font-medium"
          >
            Tarifs
          </h2>
          <p className="mt-3 max-w-xl text-nuit/70">
            Change de forfait quand tu veux. Une montée en gamme est immédiate,
            une baisse s’applique à la fin du mois.
          </p>
          <ul className="mt-10 grid gap-5 md:grid-cols-3">
            {account.plans.map((plan) => {
              const featured = plan.id === "pro";
              const active = current === plan.id;
              return (
                <li
                  key={plan.id}
                  className={cn(
                    "flex flex-col rounded-2xl border p-7",
                    featured
                      ? "border-encre ring-1 ring-encre"
                      : "border-nuit/10",
                  )}
                >
                  <div className="flex items-center justify-between gap-2">
                    <h3 className="text-lg font-semibold">{plan.name}</h3>
                    {active ? (
                      <span className="rounded-full bg-brume px-2.5 py-0.5 text-xs font-medium">
                        Ton forfait
                      </span>
                    ) : (
                      featured && (
                        <span className="rounded-full bg-encre-pale px-2.5 py-0.5 text-xs font-medium text-encre">
                          Le plus choisi
                        </span>
                      )
                    )}
                  </div>
                  <p className="mt-5 font-plume-serif text-5xl font-medium tabular-nums">
                    {money(plan.amount, plan.currency, 0)}
                    <span className="ml-2 font-plume text-base text-nuit/60">
                      par mois
                    </span>
                  </p>
                  <ul className="mt-6 mb-8 grid gap-3 text-sm">
                    {planPerks(plan).map((perk) => (
                      <li key={perk} className="flex gap-2.5">
                        <Check
                          className="size-4 shrink-0 text-encre"
                          aria-hidden="true"
                        />
                        {perk}
                      </li>
                    ))}
                  </ul>
                  <button
                    type="button"
                    disabled={api.pending !== null}
                    onClick={() => void choose(plan.id)}
                    className={cn(
                      "mt-auto",
                      featured ? plumePrimary : plumeSecondary,
                    )}
                  >
                    {api.pending === `plan:${plan.id}` && (
                      <Loader2 className="animate-spin" aria-hidden="true" />
                    )}
                    {active
                      ? "Continuer à écrire"
                      : plan.amount === 0
                        ? (currentPlan?.amount ?? 0) > 0
                          ? `Revenir à ${plan.name}`
                          : "Commencer gratuitement"
                        : `Passer à ${plan.name}`}
                  </button>
                </li>
              );
            })}
          </ul>
        </section>
      </main>

      <footer className="border-t border-nuit/10">
        <div className="mx-auto flex max-w-6xl flex-wrap justify-between gap-3 px-5 py-6 text-sm text-nuit/60 sm:px-8">
          <p>Plume, l’assistant d’écriture.</p>
          <p>Application de démonstration : les réécritures sont simulées.</p>
        </div>
      </footer>
    </>
  );
}
