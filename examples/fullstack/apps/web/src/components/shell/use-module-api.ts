import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";
import {
  isModuleSnapshot,
  request,
  type ApiResponse,
  type ModuleSnapshot,
} from "@/lib/api";
import { record } from "@/lib/devtools";
import { subscribeMessages } from "@/lib/format";

export interface RunOptions<S> {
  /** Success toast. Defaults to the subscribe action or the API message. */
  success?: (response: ApiResponse<S>) => string | null;
  /** The caller shows 402 answers itself (inline upgrade prompt). */
  inline402?: boolean;
}

/** Snapshot and request runner of one API module. Answers go to the devtools. */
export function useModuleApi<S extends ModuleSnapshot>(basePath: string) {
  const [snapshot, setSnapshot] = useState<S | null>(null);
  const [pending, setPending] = useState<string | null>("bootstrap");
  const [error, setError] = useState<string | null>(null);
  const busy = useRef(false);

  const run = useCallback(
    async (
      key: string,
      path: string,
      body: object | undefined,
      label: string,
      options: RunOptions<S> = {},
    ): Promise<ApiResponse<S> | null> => {
      if (busy.current) return null;
      busy.current = true;
      setPending(key);
      setError(null);
      try {
        const response = await request<S>(`${basePath}${path}`, body);
        const result = response.data.result;
        record({
          module: basePath,
          label,
          status: response.status,
          data: response.data,
        });
        if (!isModuleSnapshot<S>(response.data))
          throw new Error(
            "La réponse de l'API ne contient pas l'état du compte.",
          );
        setSnapshot(response.data);
        if (response.status === 402) {
          if (!options.inline402)
            toast.warning(result?.message ?? "Accès refusé par PayKit (402).");
        } else {
          const text =
            options.success?.(response) ??
            (result?.action ? subscribeMessages[result.action] : null) ??
            null;
          if (text) toast.success(text);
        }
        return response;
      } catch (cause) {
        const message =
          cause instanceof Error
            ? cause.message
            : "Impossible de contacter l'API.";
        setError(message);
        toast.error(message);
        return null;
      } finally {
        busy.current = false;
        setPending(null);
      }
    },
    [basePath],
  );

  useEffect(() => {
    void run("bootstrap", "/bootstrap", {}, "Initialisation du compte");
  }, [run]);

  return { snapshot, pending, error, run };
}

export type ModuleApi<S extends ModuleSnapshot> = ReturnType<
  typeof useModuleApi<S>
>;
