import { useSyncExternalStore } from "react";

export interface DevtoolsEntry {
  id: number;
  module: string;
  label: string;
  status: number;
  data: unknown;
  at: Date;
}

/** Every API answer, kept for the PayKit devtools panel. */
let entries: DevtoolsEntry[] = [];
let sequence = 0;
const listeners = new Set<() => void>();

export function record(entry: Omit<DevtoolsEntry, "id" | "at">) {
  entries = [{ ...entry, id: ++sequence, at: new Date() }, ...entries].slice(
    0,
    20,
  );
  listeners.forEach((listener) => listener());
}

export function useDevtools() {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    () => entries,
  );
}
