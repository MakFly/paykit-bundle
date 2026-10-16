import { useEffect, useState } from "react";

export type Model = "standard" | "expert";

export interface StoredMessage {
  role: "user" | "assistant";
  text: string;
}

export interface Conversation {
  id: string;
  title: string;
  messages: StoredMessage[];
}

const KEY = "plume-threads";
const uid = () => Math.random().toString(36).slice(2, 10);

/**
 * Conversations stay in the browser: the API only meters each rewrite.
 * The active id always exists; it is saved once it holds a message.
 */
export function useConversations() {
  const [conversations, setConversations] = useState<Conversation[]>(() => {
    try {
      const saved = JSON.parse(localStorage.getItem(KEY) ?? "[]");
      return Array.isArray(saved) ? saved : [];
    } catch {
      return [];
    }
  });
  const [activeId, setActiveId] = useState(() => conversations[0]?.id ?? uid());
  useEffect(
    () => localStorage.setItem(KEY, JSON.stringify(conversations.slice(0, 20))),
    [conversations],
  );

  return {
    conversations,
    activeId,
    active: conversations.find((c) => c.id === activeId) ?? null,
    select: setActiveId,
    startNew: () => setActiveId(uid()),
    save(id: string, messages: StoredMessage[]) {
      if (messages.length === 0) return;
      setConversations((list) => {
        const existing = list.find((c) => c.id === id);
        const title =
          existing?.title ??
          messages.find((m) => m.role === "user")?.text.slice(0, 48) ??
          "Nouveau texte";
        const next = { id, title, messages };
        return existing
          ? list.map((c) => (c.id === id ? next : c))
          : [next, ...list];
      });
    },
    remove(id: string) {
      setConversations((list) => list.filter((c) => c.id !== id));
      if (id === activeId) setActiveId(uid());
    },
  };
}
