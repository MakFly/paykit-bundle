export interface Balance {
  limit: number;
  remaining: number;
  resetAt: string | null;
  unlimited: boolean;
}

export interface Customer {
  id: string;
  name: string;
  email: string;
  subscriptions: {
    planId: string;
    status: string;
    scheduledPlanId: string | null;
    cancelAtPeriodEnd: boolean;
    currentPeriodEnd: string | null;
  }[];
  entitlements: Record<string, Balance>;
}

export interface Plan {
  id: string;
  name: string;
  amount: number;
  currency: string;
  interval: string;
  /** Feature id => limit. null: unlimited metered grant or boolean feature. */
  includes: Record<string, number | null>;
}

/** Every module snapshot carries the customer and the plans of its group. */
export interface ModuleSnapshot {
  customer: Customer;
  plans: Plan[];
}

export type SubscribeAction =
  "noop" | "resumed" | "activated" | "checkout" | "upgraded" | "scheduled";

export type ApiPayload<S, R = Record<string, unknown>> = Partial<S> & {
  error?: string;
  result?: R & { action?: SubscribeAction; message?: string };
};

export interface ApiResponse<S, R = Record<string, unknown>> {
  status: number;
  data: ApiPayload<S, R>;
}

export interface DemoModuleInfo {
  id: string;
  name: string;
  description: string;
  basePath: string;
}

export async function request<S, R = Record<string, unknown>>(
  url: string,
  body?: object,
  signal?: AbortSignal,
): Promise<ApiResponse<S, R>> {
  const response = await fetch(url, {
    method: body ? "POST" : "GET",
    headers: {
      Accept: "application/json",
      ...(body ? { "Content-Type": "application/json" } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
    signal,
  });
  const contentType = response.headers.get("content-type") ?? "";
  if (!contentType.includes("application/json")) {
    throw new Error(
      "L'API Symfony ne répond pas en JSON. Vérifie le terminal de l'API et sa configuration.",
    );
  }
  const data = (await response.json()) as ApiPayload<S, R>;
  // 402 is a business answer (quota, missing perk), not a transport error.
  if (!response.ok && response.status !== 402) {
    throw new Error(data.error ?? `Erreur API (${response.status}).`);
  }
  return { status: response.status, data };
}

export function isModuleSnapshot<S extends ModuleSnapshot>(
  data: Partial<S>,
): data is S {
  return Boolean(data.customer && data.plans);
}
