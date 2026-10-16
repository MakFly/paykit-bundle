import type { ReactNode } from "react";
import {
  Coffee,
  Headphones,
  Keyboard,
  LampDesk,
  Laptop,
  Minus,
  Mouse,
  NotebookPen,
  Package,
  Plus,
  Square,
  type LucideIcon,
} from "lucide-react";
import { money } from "@/lib/format";
import { cn } from "@/lib/utils";
import type { Product } from "./types";

const icons: Record<string, LucideIcon> = {
  headphones: Headphones,
  keyboard: Keyboard,
  mouse: Mouse,
  lamp: LampDesk,
  laptop: Laptop,
  square: Square,
  notebook: NotebookPen,
  coffee: Coffee,
};

export const buttonPrimary =
  "inline-flex items-center justify-center gap-2 rounded-full bg-ardoise px-6 py-3 text-sm font-semibold text-craie outline-none transition-colors hover:bg-ardoise/85 focus-visible:ring-2 focus-visible:ring-ardoise focus-visible:ring-offset-2 focus-visible:ring-offset-beton disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-4";
export const buttonGhost =
  "inline-flex items-center justify-center gap-2 rounded-full border border-ardoise/25 px-5 py-2.5 text-sm font-medium text-ardoise outline-none transition-colors hover:border-ardoise hover:bg-ardoise/5 focus-visible:ring-2 focus-visible:ring-ardoise disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-4";
export const focusRing =
  "outline-none focus-visible:ring-2 focus-visible:ring-ardoise focus-visible:ring-offset-2 focus-visible:ring-offset-beton";

/** The product "photo": the object drawn on a tinted plinth with its shadow. */
export function ProductStage({
  product,
  size = "md",
  className,
}: {
  product: Product;
  size?: "sm" | "md" | "lg";
  className?: string;
}) {
  const Icon = icons[product.icon] ?? Package;
  return (
    <div
      className={cn(
        "relative grid place-items-center overflow-hidden",
        size === "sm" ? "rounded-xl" : "rounded-[1.75rem]",
        className,
      )}
      style={{ backgroundColor: product.tint }}
    >
      {size !== "sm" && (
        <span
          aria-hidden="true"
          className={cn(
            "absolute h-[5%] w-[40%] rounded-[50%] bg-ardoise/25 blur-md",
            size === "lg" ? "bottom-[27%]" : "bottom-[29%]",
          )}
        />
      )}
      <Icon
        aria-hidden="true"
        strokeWidth={size === "sm" ? 1.5 : 1}
        className={cn(
          "relative text-ardoise/85",
          size === "sm" && "size-8",
          size === "md" && "size-[38%]",
          size === "lg" && "size-[42%]",
        )}
      />
    </div>
  );
}

/** Yellow workshop-tape label: the Club's mark across the store. */
export function Tape({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-block -rotate-2 bg-ruban px-2 py-0.5 font-etabli-display text-sm font-bold text-ardoise",
        className,
      )}
    >
      {children}
    </span>
  );
}

export function Price({
  product,
  member,
  large = false,
}: {
  product: Product;
  member: boolean;
  large?: boolean;
}) {
  const size = large ? "text-3xl" : "text-lg";
  if (member) {
    return (
      <p className="flex flex-wrap items-center gap-x-3 gap-y-1 tabular-nums">
        <span className={cn("font-etabli-display font-semibold", size)}>
          {money(product.memberPrice)}
        </span>
        <Tape className={large ? "" : "text-xs"}>Prix Club</Tape>
        <s className="text-sm text-ardoise-soft">{money(product.price)}</s>
      </p>
    );
  }
  return (
    <p className={cn("font-etabli-display font-semibold tabular-nums", size)}>
      {money(product.price)}
    </p>
  );
}

export function QuantityStepper({
  value,
  onChange,
  label,
}: {
  value: number;
  onChange: (value: number) => void;
  label: string;
}) {
  const step = cn(
    "grid size-9 place-items-center rounded-full text-ardoise hover:bg-ardoise/10 disabled:opacity-30",
    focusRing,
  );
  return (
    <div
      role="group"
      aria-label={`Quantité de ${label}`}
      className="inline-flex items-center rounded-full border border-ardoise/25"
    >
      <button
        type="button"
        className={step}
        aria-label="Retirer un"
        onClick={() => onChange(value - 1)}
        disabled={value <= 0}
      >
        <Minus className="size-4" />
      </button>
      <span
        className="w-7 text-center text-sm font-semibold tabular-nums"
        aria-live="polite"
      >
        {value}
      </span>
      <button
        type="button"
        className={step}
        aria-label="Ajouter un"
        onClick={() => onChange(value + 1)}
        disabled={value >= 10}
      >
        <Plus className="size-4" />
      </button>
    </div>
  );
}
