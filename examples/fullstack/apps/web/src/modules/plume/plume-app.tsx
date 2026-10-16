import { createContext, useContext, useEffect, type ReactNode } from "react";
import { CreditCard, Home, PenLine, Plus, Trash2 } from "lucide-react";
import {
  useModuleApi,
  type ModuleApi,
} from "@/components/shell/use-module-api";
import type { ModuleSnapshot, Plan } from "@/lib/api";
import { ModeToggle } from "@/components/mode-toggle";
import { Progress } from "@/components/ui/progress";
import { date } from "@/lib/format";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";
import { BillingPage } from "./billing-page";
import { useConversations } from "./conversations";
import { LandingPage } from "./landing-page";
import { WorkspacePage } from "./workspace-page";

export interface PlumeSnapshot extends ModuleSnapshot {
  proAllowed: boolean;
}

interface Plume {
  api: ModuleApi<PlumeSnapshot>;
  account: PlumeSnapshot;
  chats: ReturnType<typeof useConversations>;
}

const PlumeContext = createContext<Plume | null>(null);

export function usePlume() {
  const plume = useContext(PlumeContext);
  if (!plume) throw new Error("usePlume outside PlumeApp");
  return plume;
}

export const plumeFocus =
  "outline-none focus-visible:ring-2 focus-visible:ring-encre focus-visible:ring-offset-2";
/** Focus ring of the shadcn-styled app pages. */
export const appFocus =
  "outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50";
export const plumePrimary = cn(
  "inline-flex items-center justify-center gap-2 rounded-lg bg-encre px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-encre/90 disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-4",
  plumeFocus,
);
export const plumeSecondary = cn(
  "inline-flex items-center justify-center gap-2 rounded-lg border border-nuit/15 bg-white px-5 py-2.5 text-sm font-semibold text-nuit transition-colors hover:border-nuit/30 disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-4",
  plumeFocus,
);

export function Wordmark({ className }: { className?: string }) {
  return (
    <span
      className={cn(
        "font-plume-serif text-2xl font-semibold tracking-tight italic",
        className,
      )}
    >
      Plume
    </span>
  );
}

export function planPerks(plan: Plan) {
  return [
    !("messages" in plan.includes)
      ? "Aucune réécriture"
      : plan.includes.messages === null
        ? "Réécritures illimitées"
        : `${plan.includes.messages} réécritures par mois`,
    "pro_models" in plan.includes
      ? "Modèle Expert : structure et résumé"
      : "Modèle Standard",
  ];
}

export function PlumeApp({ route }: { route: string }) {
  const api = useModuleApi<PlumeSnapshot>("/api/saas");
  const chats = useConversations();
  const page = route.split("/")[1] ?? "";

  useEffect(() => {
    document.title = "Plume, l’assistant d’écriture";
  }, []);

  const account = api.snapshot;
  const inApp = page === "app" || page === "facturation";
  return (
    <div
      className={cn(
        "min-h-svh",
        // The marketing site wears the Plume brand; the app is plain shadcn.
        inApp
          ? "bg-background font-sans text-foreground"
          : "bg-white font-plume text-nuit",
      )}
    >
      {!account ? (
        <div role="status" className="grid min-h-svh place-items-center">
          <Wordmark className="animate-pulse text-4xl text-nuit/40" />
          <span className="sr-only">Chargement de Plume</span>
        </div>
      ) : (
        <PlumeContext.Provider value={{ api, account, chats }}>
          {page === "app" ? (
            // The writing space is a clone of the assistant-ui Claude demo, with its own rail.
            <WorkspacePage />
          ) : page === "facturation" ? (
            <AppLayout>
              <BillingPage />
            </AppLayout>
          ) : (
            <LandingPage />
          )}
        </PlumeContext.Provider>
      )}
    </div>
  );
}

export function UsageMeter({ compact = false }: { compact?: boolean }) {
  const { account } = usePlume();
  const balance = account.customer.entitlements.messages;
  const plan = account.plans.find(
    (p) => p.id === account.customer.subscriptions[0]?.planId,
  );
  if (!balance) return null;
  const ratio = balance.unlimited
    ? 0
    : ((balance.limit - balance.remaining) / Math.max(1, balance.limit)) * 100;
  return (
    <div
      className={cn("grid gap-2", !compact && "rounded-lg border bg-card p-3")}
    >
      <div className="flex items-baseline justify-between gap-2 text-sm">
        <span className="font-medium">{plan?.name ?? "Forfait"}</span>
        <span className="text-muted-foreground tabular-nums">
          {balance.unlimited
            ? "Illimité"
            : `${balance.remaining} sur ${balance.limit}`}
        </span>
      </div>
      {!balance.unlimited && (
        <Progress
          aria-label="Réécritures utilisées ce mois-ci"
          value={ratio}
          indicatorClassName={cn(
            ratio >= 100 && "bg-destructive",
            ratio >= 80 && ratio < 100 && "bg-warning",
          )}
        />
      )}
      {!compact && !balance.unlimited && (
        <p className="text-xs text-muted-foreground">
          Renouvelé le {date(balance.resetAt)}.
        </p>
      )}
    </div>
  );
}

function AppLayout({ children }: { children: ReactNode }) {
  const { chats } = usePlume();
  const route = location.hash;
  const links = [
    { to: "/plume/app", label: "Écrire", short: "Écrire", icon: PenLine },
    {
      to: "/plume/facturation",
      label: "Forfait et facturation",
      short: "Forfait",
      icon: CreditCard,
    },
    { to: "/plume", label: "Site de Plume", short: "Site", icon: Home },
  ];
  return (
    <div className="flex h-svh flex-col md:flex-row">
      <aside className="flex shrink-0 flex-col gap-4 border-b bg-muted/40 p-3 md:w-64 md:border-r md:border-b-0">
        <div className="flex items-center justify-between">
          <a href={href("/plume/app")} className={cn("rounded", appFocus)}>
            <Wordmark className="px-2 text-xl" />
          </a>
          <div className="flex items-center gap-2">
            <div className="w-40 md:hidden">
              <UsageMeter compact />
            </div>
            <ModeToggle />
          </div>
        </div>
        <nav
          aria-label="Plume"
          className="flex gap-1 overflow-x-auto md:flex-col"
        >
          {links.map(({ to, label, short, icon: Icon }) => {
            const active = route === `#${to}`;
            return (
              <a
                key={to}
                href={href(to)}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex shrink-0 items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium",
                  active
                    ? "bg-accent text-accent-foreground"
                    : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
                  appFocus,
                )}
              >
                <Icon className="size-4" aria-hidden="true" />
                <span className="md:hidden">{short}</span>
                <span className="hidden md:inline">{label}</span>
              </a>
            );
          })}
        </nav>
        <section
          aria-labelledby="plume-history"
          className="hidden min-h-0 flex-1 flex-col md:flex"
        >
          <div className="flex items-center justify-between px-2">
            <h2
              id="plume-history"
              className="text-xs font-medium text-muted-foreground"
            >
              Textes récents
            </h2>
            <a
              href={href("/plume/app")}
              onClick={() => chats.startNew()}
              aria-label="Nouveau texte"
              className={cn(
                "grid size-7 place-items-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground",
                appFocus,
              )}
            >
              <Plus className="size-4" />
            </a>
          </div>
          <ul className="mt-2 min-h-0 flex-1 overflow-y-auto">
            {chats.conversations.length === 0 && (
              <li className="px-2 py-1.5 text-sm text-muted-foreground">
                Aucun texte pour l’instant.
              </li>
            )}
            {chats.conversations.map((conversation) => (
              <li key={conversation.id} className="group flex items-center">
                <a
                  href={href("/plume/app")}
                  onClick={() => chats.select(conversation.id)}
                  aria-current={
                    chats.active?.id === conversation.id &&
                    route === "#/plume/app"
                      ? "true"
                      : undefined
                  }
                  className={cn(
                    "min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-sm",
                    chats.active?.id === conversation.id
                      ? "bg-accent text-accent-foreground"
                      : "text-muted-foreground hover:bg-accent/60 hover:text-foreground",
                    appFocus,
                  )}
                >
                  {conversation.title}
                </a>
                <button
                  type="button"
                  onClick={() => chats.remove(conversation.id)}
                  aria-label={`Supprimer « ${conversation.title} »`}
                  className={cn(
                    "grid size-7 shrink-0 place-items-center rounded-md text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-foreground focus-visible:opacity-100",
                    appFocus,
                  )}
                >
                  <Trash2 className="size-3.5" />
                </button>
              </li>
            ))}
          </ul>
        </section>
        <div className="hidden md:block">
          <UsageMeter />
        </div>
      </aside>
      <div className="min-h-0 min-w-0 flex-1">{children}</div>
    </div>
  );
}
