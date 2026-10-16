import { createContext, useContext, useEffect, useState } from "react";
import { ShoppingBag } from "lucide-react";
import {
  useModuleApi,
  type ModuleApi,
} from "@/components/shell/use-module-api";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";
import { CartPage } from "./cart-page";
import { ClubPage } from "./club-page";
import { HomePage } from "./home-page";
import { OrderPage, OrdersPage } from "./orders-page";
import { focusRing, Tape } from "./parts";
import { ProductPage } from "./product-page";
import type { CartLine, ShopSnapshot } from "./types";

const CART_KEY = "etabli-cart";

function useCart() {
  const [lines, setLines] = useState<CartLine[]>(() => {
    try {
      const saved = JSON.parse(localStorage.getItem(CART_KEY) ?? "[]");
      return Array.isArray(saved) ? saved : [];
    } catch {
      return [];
    }
  });
  useEffect(
    () => localStorage.setItem(CART_KEY, JSON.stringify(lines)),
    [lines],
  );
  const set = (productId: string, quantity: number) =>
    setLines((current) => {
      const next = Math.max(0, Math.min(10, quantity));
      if (next === 0) return current.filter((l) => l.productId !== productId);
      return current.some((l) => l.productId === productId)
        ? current.map((l) =>
            l.productId === productId ? { ...l, quantity: next } : l,
          )
        : [...current, { productId, quantity: next }];
    });
  const quantity = (productId: string) =>
    lines.find((l) => l.productId === productId)?.quantity ?? 0;
  return {
    lines,
    set,
    quantity,
    add: (productId: string, amount = 1) =>
      set(productId, quantity(productId) + amount),
    clear: () => setLines([]),
  };
}

interface Store {
  api: ModuleApi<ShopSnapshot>;
  shop: ShopSnapshot;
  cart: ReturnType<typeof useCart>;
}

const StoreContext = createContext<Store | null>(null);

export function useStore() {
  const store = useContext(StoreContext);
  if (!store) throw new Error("useStore outside EtabliApp");
  return store;
}

export function EtabliApp({ route }: { route: string }) {
  const api = useModuleApi<ShopSnapshot>("/api/shop");
  const cart = useCart();
  const shop = api.snapshot;
  // Products removed from the catalog since the cart was saved are ignored.
  const lines = shop
    ? cart.lines.filter((l) => shop.products.some((p) => p.id === l.productId))
    : [];
  const count = lines.reduce((sum, l) => sum + l.quantity, 0);
  const plan = shop?.customer.subscriptions[0]?.planId;
  const member = plan !== undefined && plan !== "club_none";
  const [, section, param] = route.split("/");

  useEffect(() => {
    document.title = "Établi, objets pour le bureau";
  }, []);

  const nav = [
    {
      to: "/etabli",
      label: "Boutique",
      active: !section || section === "produit",
    },
    { to: "/etabli/club", label: "Club", active: section === "club" },
    {
      to: "/etabli/commandes",
      label: "Commandes",
      active: section === "commandes" || section === "commande",
    },
  ];

  return (
    <div className="flex min-h-svh flex-col bg-beton font-etabli text-ardoise">
      <a
        href="#etabli-main"
        onClick={(event) => {
          event.preventDefault();
          document.getElementById("etabli-main")?.focus();
        }}
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-ardoise focus:px-4 focus:py-2 focus:text-craie"
      >
        Aller au contenu
      </a>
      <header className="border-b border-ardoise/10">
        <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-5 sm:px-8">
          <a
            href={href("/etabli")}
            className={cn(
              "rounded-md font-etabli-display text-2xl font-extrabold tracking-tight",
              focusRing,
            )}
          >
            Établi
          </a>
          <nav aria-label="Principale" className="hidden gap-1 sm:flex">
            {nav.map((item) => (
              <a
                key={item.to}
                href={href(item.to)}
                aria-current={item.active ? "page" : undefined}
                className={cn(
                  "rounded-full px-3 py-1.5 text-sm font-medium",
                  item.active
                    ? "bg-ardoise/10"
                    : "text-ardoise-soft hover:text-ardoise",
                  focusRing,
                )}
              >
                {item.label}
              </a>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-3">
            {member && (
              <a
                href={href("/etabli/club")}
                className={cn("rounded", focusRing)}
              >
                <Tape className="text-xs">
                  {shop?.plans.find((p) => p.id === plan)?.name ?? "Club"}
                </Tape>
              </a>
            )}
            <a
              href={href("/etabli/panier")}
              aria-label={`Panier, ${count} article${count > 1 ? "s" : ""}`}
              className={cn(
                "flex items-center gap-2 rounded-full bg-ardoise py-2 pr-4 pl-3 text-sm font-semibold text-craie hover:bg-ardoise/85",
                focusRing,
              )}
            >
              <ShoppingBag className="size-4" aria-hidden="true" />
              <span className="tabular-nums">{count}</span>
            </a>
          </div>
        </div>
        <nav
          aria-label="Principale mobile"
          className="flex gap-1 overflow-x-auto px-5 pb-3 sm:hidden"
        >
          {nav.map((item) => (
            <a
              key={item.to}
              href={href(item.to)}
              aria-current={item.active ? "page" : undefined}
              className={cn(
                "rounded-full px-3 py-1.5 text-sm font-medium",
                item.active ? "bg-ardoise/10" : "text-ardoise-soft",
                focusRing,
              )}
            >
              {item.label}
            </a>
          ))}
        </nav>
      </header>

      <main
        id="etabli-main"
        tabIndex={-1}
        className="mx-auto w-full max-w-6xl flex-1 px-5 py-10 outline-none sm:px-8 lg:py-14"
      >
        {!shop ? (
          <div role="status" className="grid gap-6 md:grid-cols-2">
            <span className="sr-only">Chargement de la boutique</span>
            <div className="h-80 animate-pulse rounded-[1.75rem] bg-ardoise/5" />
            <div className="h-80 animate-pulse rounded-[1.75rem] bg-ardoise/5" />
          </div>
        ) : (
          <StoreContext.Provider
            value={{ api, shop, cart: { ...cart, lines } }}
          >
            {section === "produit" && param ? (
              <ProductPage id={param} />
            ) : section === "panier" ? (
              <CartPage />
            ) : section === "club" ? (
              <ClubPage />
            ) : section === "commandes" ? (
              <OrdersPage />
            ) : section === "commande" && param ? (
              <OrderPage id={param} />
            ) : (
              <HomePage />
            )}
          </StoreContext.Provider>
        )}
      </main>

      <footer className="border-t border-ardoise/10">
        <div className="mx-auto flex max-w-6xl flex-wrap justify-between gap-3 px-5 py-6 text-sm text-ardoise-soft sm:px-8">
          <p>Établi, objets pour bien travailler.</p>
          <p>Boutique de démonstration : aucun paiement réel.</p>
        </div>
      </footer>
    </div>
  );
}
