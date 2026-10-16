import { useEffect, useState } from "react";
import { Braces, X } from "lucide-react";
import { useDevtools } from "@/lib/devtools";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";

const scenarios = [
  { route: "/etabli", name: "Établi", detail: "Boutique, adhésion Club" },
  { route: "/plume", name: "Plume", detail: "SaaS, quota et modèle Pro" },
];

/**
 * Developer overlay shared by both demo sites: switch scenario and read the raw
 * PayKit answers without leaving the page.
 */
export function Devtools({ route }: { route: string }) {
  const entries = useDevtools();
  const [open, setOpen] = useState(false);
  const [selected, setSelected] = useState<number | null>(null);
  const entry = entries.find((item) => item.id === selected) ?? entries[0];

  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open]);

  return (
    <div className="fixed bottom-4 left-4 z-50 flex flex-col items-start font-sans sm:right-4 sm:left-auto sm:items-end">
      {open ? (
        <section
          aria-label="PayKit devtools"
          className="flex h-[min(36rem,calc(100svh-2rem))] w-[min(26rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border border-neutral-800 bg-neutral-950 text-neutral-100 shadow-2xl"
        >
          <header className="flex items-center gap-2 border-b border-neutral-800 px-4 py-3">
            <Braces className="size-4 text-neutral-400" aria-hidden="true" />
            <h2 className="text-sm font-medium">PayKit devtools</h2>
            <button
              type="button"
              onClick={() => setOpen(false)}
              aria-label="Fermer les devtools"
              className="ml-auto grid size-7 place-items-center rounded-md text-neutral-400 outline-none hover:bg-neutral-800 hover:text-white focus-visible:ring-2 focus-visible:ring-neutral-500"
            >
              <X className="size-4" />
            </button>
          </header>
          <nav
            aria-label="Scénario"
            className="grid grid-cols-2 gap-2 border-b border-neutral-800 p-3"
          >
            {scenarios.map((scenario) => {
              const active = route.startsWith(scenario.route);
              return (
                <a
                  key={scenario.route}
                  href={href(scenario.route)}
                  aria-current={active ? "page" : undefined}
                  className={cn(
                    "rounded-lg border px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-neutral-500",
                    active
                      ? "border-neutral-500 bg-neutral-800"
                      : "border-neutral-800 hover:bg-neutral-900",
                  )}
                >
                  <span className="block font-medium">{scenario.name}</span>
                  <span className="text-xs text-neutral-400">
                    {scenario.detail}
                  </span>
                </a>
              );
            })}
          </nav>
          {entries.length === 0 ? (
            <p className="grid flex-1 place-items-center p-6 text-center text-sm text-neutral-400">
              Les réponses de l’API apparaîtront ici.
            </p>
          ) : (
            <>
              <ol className="max-h-40 shrink-0 divide-y divide-neutral-800 overflow-auto border-b border-neutral-800 text-sm">
                {entries.map((item) => (
                  <li key={item.id}>
                    <button
                      type="button"
                      onClick={() => setSelected(item.id)}
                      aria-pressed={item.id === entry?.id}
                      className={cn(
                        "flex w-full items-center gap-3 px-4 py-2 text-left outline-none focus-visible:bg-neutral-800",
                        item.id === entry?.id
                          ? "bg-neutral-900"
                          : "hover:bg-neutral-900",
                      )}
                    >
                      <span
                        className={cn(
                          "font-mono text-xs",
                          item.status >= 400
                            ? "text-amber-400"
                            : "text-emerald-400",
                        )}
                      >
                        {item.status}
                      </span>
                      <span className="min-w-0 flex-1 truncate">
                        {item.label}
                      </span>
                      <span className="text-xs text-neutral-500">
                        {item.module.replace("/api/", "")}
                      </span>
                    </button>
                  </li>
                ))}
              </ol>
              <pre
                tabIndex={0}
                aria-label="Réponse JSON sélectionnée"
                className="min-h-0 flex-1 overflow-auto p-4 font-mono text-xs leading-relaxed text-neutral-300 outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-inset"
              >
                {JSON.stringify(entry?.data ?? {}, null, 2)}
              </pre>
            </>
          )}
        </section>
      ) : (
        <button
          type="button"
          onClick={() => setOpen(true)}
          aria-label="Ouvrir les devtools PayKit"
          className="flex items-center gap-2 rounded-full bg-neutral-950 p-2.5 text-sm text-white sm:py-2 sm:pr-4 sm:pl-3 shadow-lg outline-none hover:bg-neutral-800 focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2"
        >
          <Braces className="size-4" aria-hidden="true" />
          <span className="hidden sm:inline">PayKit</span>
          {entries.length > 0 && (
            <span
              className={cn(
                "size-2 rounded-full",
                entries[0].status >= 400 ? "bg-amber-400" : "bg-emerald-400",
              )}
              aria-label={`Dernière réponse HTTP ${entries[0].status}`}
            />
          )}
        </button>
      )}
    </div>
  );
}
