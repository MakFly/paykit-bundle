import { useSyncExternalStore, type MouseEvent } from "react";

/** Minimal hash router: "#/etabli/produit/lampe-led" gives "/etabli/produit/lampe-led". */
function read() {
  return location.hash.replace(/^#/, "") || "/";
}

function subscribe(onChange: () => void) {
  window.addEventListener("hashchange", onChange);
  return () => window.removeEventListener("hashchange", onChange);
}

export function useRoute() {
  return useSyncExternalStore(subscribe, read);
}

export function navigate(to: string) {
  if (read() !== to) {
    location.hash = to;
    window.scrollTo({ top: 0 });
  }
}

export const href = (to: string) => `#${to}`;

/** onClick for links that must also scroll to the top. */
export const go = (to: string) => (event: MouseEvent) => {
  event.preventDefault();
  navigate(to);
};
