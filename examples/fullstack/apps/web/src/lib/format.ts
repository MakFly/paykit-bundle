export const money = (amount: number, currency = "eur", digits = 2) =>
  new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency,
    minimumFractionDigits: amount % 100 === 0 ? 0 : digits,
    maximumFractionDigits: digits,
  }).format(amount / 100);

export const date = (value: string | null | undefined) =>
  value
    ? new Intl.DateTimeFormat("fr-FR", { dateStyle: "medium" }).format(
        new Date(value),
      )
    : "fin de période";

export const subscribeMessages: Record<string, string> = {
  activated: "Abonnement activé.",
  upgraded: "Abonnement mis à niveau.",
  scheduled:
    "Changement programmé en fin de période. Tes accès sont conservés jusque-là.",
  resumed: "Changement programmé annulé.",
  noop: "Cette offre est déjà active.",
};
