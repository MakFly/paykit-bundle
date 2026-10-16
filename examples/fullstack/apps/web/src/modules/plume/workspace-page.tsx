import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type FC,
} from "react";
import {
  ActionBarPrimitive,
  AssistantRuntimeProvider,
  AttachmentPrimitive,
  AuiIf,
  ComposerPrimitive,
  MessagePrimitive,
  SimpleTextAttachmentAdapter,
  ThreadPrimitive,
  useAuiState,
  useLocalRuntime,
  WebSpeechDictationAdapter,
  type ChatModelAdapter,
} from "@assistant-ui/react";
import {
  ArrowUpIcon,
  AudioLines,
  CheckIcon,
  ChevronDownIcon,
  ClipboardIcon,
  LockIcon,
  Mail,
  Megaphone,
  PencilIcon,
  PlusIcon,
  RefreshCwIcon,
  Repeat,
  Sparkle,
  ThumbsDown,
  ThumbsUp,
  XIcon,
} from "lucide-react";
import { useAttachmentSrc } from "@/hooks/use-attachment-src";
import { MarkdownText } from "@/components/assistant-ui/elements/markdown-text";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { href } from "@/lib/router";
import { ClaudeMobileRail, ClaudeRail } from "./claude-rail";
import type { Model, StoredMessage } from "./conversations";
import { usePlume } from "./plume-app";

/*
 * Visual clone of the assistant-ui Claude demo
 * (apps/docs/components/pages/examples/claude.tsx, MIT), wired to PayKit:
 * each turn calls POST /api/saas/chat, which meters one message.
 */

interface ChatResult {
  reply?: { model: Model; text: string };
  reason?: "quota" | "model";
  message?: string;
}

interface Blocked {
  reason: "quota" | "model";
  message: string;
}

const MODELS: { id: Model; name: string; description: string }[] = [
  {
    id: "standard",
    name: "Standard",
    description: "Retire les tics de langage, garde ton texte",
  },
  {
    id: "expert",
    name: "Expert",
    description: "Découpe en paragraphes et résume, avec Pro",
  },
];

const SAMPLES = [
  {
    label: "Courriel",
    Icon: Mail,
    text: "En fait, je voulais juste vous dire que la réunion de demain est vraiment décalée à 15 h, du coup merci de prévenir l’équipe.",
  },
  {
    label: "Relance",
    Icon: Repeat,
    text: "bonjour, je me permets de vous relancer un peu concernant la facture de septembre qui est globalement toujours en attente",
  },
  {
    label: "Annonce",
    Icon: Megaphone,
    text: "Notre nouvelle lampe est carrément plus douce pour les yeux. elle se règle en un geste et du coup elle trouve sa place sur tous les bureaux.",
  },
];

const messageActionButtonClassName =
  "flex size-8 items-center justify-center rounded-md text-[#5b5950] dark:text-[#a3a098] transition-colors hover:bg-[#1a1a18]/5 dark:hover:bg-white/5 hover:text-[#1a1a18] dark:hover:text-[#eee]";

const SessionContext = createContext<{
  model: Model;
  setModel: (model: Model) => void;
  expertAllowed: boolean;
  blocked: Blocked | null;
  dismiss: () => void;
  fill: (text: string) => void;
}>({
  model: "standard",
  setModel: () => {},
  expertAllowed: false,
  blocked: null,
  dismiss: () => {},
  fill: () => {},
});

const textOf = (content: readonly { type: string; text?: string }[]) =>
  content
    .map((part) => (part.type === "text" ? (part.text ?? "") : ""))
    .join("");

// A dropped .txt or .md file is rewritten like a pasted draft.
const attachmentText = (
  attachments: readonly {
    content?: readonly { type: string; text?: string }[];
  }[] = [],
) =>
  attachments
    .map((a) =>
      textOf(a.content ?? []).replace(
        /^<attachment[^>]*>\n?|\n?<\/attachment>$/g,
        "",
      ),
    )
    .join("\n\n");

export function WorkspacePage() {
  const { chats } = usePlume();
  const empty = !chats.active;
  return (
    <div className="relative flex h-svh w-full overflow-hidden">
      <ClaudeRail empty={empty} />
      <ClaudeMobileRail empty={empty} />
      <div className="min-w-0 flex-1 overflow-hidden">
        <ChatSession
          key={chats.activeId}
          id={chats.activeId}
          initial={chats.active?.messages ?? []}
        />
      </div>
    </div>
  );
}

function ChatSession({
  id,
  initial,
}: {
  id: string;
  initial: StoredMessage[];
}) {
  const { api, account, chats } = usePlume();
  const [model, setModel] = useState<Model>("standard");
  const [blocked, setBlocked] = useState<Blocked | null>(null);
  const modelRef = useRef(model);
  modelRef.current = model;
  const runRef = useRef(api.run);
  runRef.current = api.run;

  const adapter = useMemo<ChatModelAdapter>(
    () => ({
      async run({ messages }) {
        const last = messages.at(-1);
        const prompt = last
          ? [textOf(last.content), attachmentText(last.attachments)]
              .filter((text) => text.trim() !== "")
              .join("\n\n")
          : "";
        if (prompt.length > 2000)
          throw new Error("Le texte dépasse 2000 caractères.");
        const current = modelRef.current;
        const response = await runRef.current(
          "chat",
          "/chat",
          { prompt, model: current },
          current === "expert" ? "Réécriture Expert" : "Réécriture Standard",
          { inline402: true, success: () => null },
        );
        const result = response?.data.result as ChatResult | undefined;
        if (!response || !result)
          throw new Error("Plume n’a pas pu joindre l’API. Réessaie.");
        if (response.status === 402 && result.reason) {
          const message = result.message ?? "Action refusée par ton forfait.";
          setBlocked({ reason: result.reason, message });
          throw new Error(message);
        }
        setBlocked(null);
        return { content: [{ type: "text", text: result.reply?.text ?? "" }] };
      },
    }),
    [],
  );

  const adapters = useMemo(() => {
    const attachments = new SimpleTextAttachmentAdapter();
    attachments.accept = "text/plain,text/markdown,.txt,.md";
    return {
      attachments,
      dictation: WebSpeechDictationAdapter.isSupported()
        ? new WebSpeechDictationAdapter({ language: "fr-FR" })
        : undefined,
    };
  }, []);

  const runtime = useLocalRuntime(adapter, {
    adapters,
    initialMessages: initial.map((m) => ({
      role: m.role,
      content: [{ type: "text" as const, text: m.text }],
    })),
  });

  const saveRef = useRef(chats.save);
  saveRef.current = chats.save;
  useEffect(
    () =>
      runtime.thread.subscribe(() => {
        const state = runtime.thread.getState();
        if (state.isRunning) return;
        saveRef.current(
          id,
          state.messages
            .filter((m) => m.role === "user" || m.role === "assistant")
            .map((m) => ({
              role: m.role as StoredMessage["role"],
              text: textOf(m.content),
            }))
            .filter((m) => m.text !== ""),
        );
      }),
    [runtime, id],
  );

  return (
    <SessionContext.Provider
      value={{
        model,
        setModel,
        expertAllowed: account.proAllowed,
        blocked,
        dismiss: () => setBlocked(null),
        fill: (text) => runtime.thread.composer.setText(text),
      }}
    >
      <AssistantRuntimeProvider runtime={runtime}>
        <ThreadPrimitive.Root className="flex h-full flex-col items-stretch bg-[#F0ECE0] font-serif dark:bg-[#2b2a27] text-[#1a1a18] dark:text-[#eee]">
          <AuiIf condition={(s) => s.thread.isEmpty}>
            <EmptyState />
          </AuiIf>

          <AuiIf condition={(s) => !s.thread.isEmpty}>
            <ThreadPrimitive.Viewport className="flex grow flex-col overflow-y-auto px-4 pt-12">
              <ThreadPrimitive.Messages>
                {() => <ChatMessage />}
              </ThreadPrimitive.Messages>

              <ThreadPrimitive.ViewportFooter className="sticky bottom-0 mx-auto mt-auto w-full max-w-3xl bg-linear-to-b from-transparent via-[#F0ECE0]/85 to-[#F0ECE0] pt-4 pb-2 dark:via-[#2b2a27]/85 dark:to-[#2b2a27]">
                <Composer />
                <p className="pt-2 text-center text-xs text-[#8a8780] dark:text-[#a3a098]">
                  Plume peut se tromper. Relis toujours le texte avant de
                  l’envoyer.
                </p>
              </ThreadPrimitive.ViewportFooter>
            </ThreadPrimitive.Viewport>
          </AuiIf>
        </ThreadPrimitive.Root>
      </AssistantRuntimeProvider>
    </SessionContext.Provider>
  );
}

const EmptyState: FC = () => {
  return (
    <div className="flex grow flex-col items-center justify-center px-4">
      <div className="mx-auto flex w-full max-w-2xl flex-col items-stretch gap-5">
        <p className="flex items-center justify-center gap-3 font-serif text-3xl text-[#1a1a18] dark:text-[#eee] sm:text-4xl">
          <Sparkle className="size-7 fill-[#c96442] text-[#c96442]" />
          <span>Qu’est-ce qu’on réécrit ?</span>
        </p>
        <Composer />
        <ModeTabs />
      </div>
    </div>
  );
};

/** Plan refusal (HTTP 402), shown where the demo has nothing: above the composer. */
const BlockedNotice: FC = () => {
  const { blocked, dismiss, setModel } = useContext(SessionContext);
  if (!blocked) return null;
  return (
    <div
      role="alert"
      className="mb-2 flex flex-col gap-3 rounded-2xl border border-[#E5E0D6] dark:border-[#3d3a35] bg-white dark:bg-[#1f1e1b] px-4 py-3 text-sm sm:flex-row sm:items-center"
    >
      <div className="flex-1">
        <p className="text-[#1a1a18] dark:text-[#eee]">{blocked.message}</p>
        <p className="text-[#8a8780] dark:text-[#a3a098]">
          {blocked.reason === "quota"
            ? "Rien n’a été décompté. Pro donne 100 réécritures par mois."
            : "Rien n’a été décompté. Relance avec Standard ou passe à Pro."}
        </p>
      </div>
      <div className="flex shrink-0 items-center gap-1">
        {blocked.reason === "model" && (
          <button
            type="button"
            onClick={() => {
              setModel("standard");
              dismiss();
            }}
            className="flex h-8 items-center rounded-md px-2.5 whitespace-nowrap text-[#1a1a18] dark:text-[#eee] transition hover:bg-[#1a1a18]/5 dark:hover:bg-white/5"
          >
            Passer en Standard
          </button>
        )}
        <a
          href={href("/plume/facturation")}
          className="flex h-8 items-center rounded-md bg-[#c96442] px-3 whitespace-nowrap text-white transition-colors hover:bg-[#b1573a]"
        >
          Voir les forfaits
        </a>
      </div>
    </div>
  );
};

const Composer: FC = () => {
  return (
    <div className="flex w-full flex-col">
      <BlockedNotice />
      <ComposerPrimitive.Root className="flex w-full flex-col gap-2 rounded-2xl border border-[#E5E0D6] dark:border-[#3d3a35] bg-white dark:bg-[#1f1e1b] px-3.5 pt-3 pb-2.5">
        <ComposerPrimitive.Input
          placeholder="Colle ou écris ton brouillon…"
          aria-label="Ton brouillon"
          rows={1}
          className="block max-h-72 min-h-6 w-full resize-none bg-transparent text-[#1a1a18] dark:text-[#eee] outline-none placeholder:text-[#9a9893] dark:placeholder:text-[#9a9893]"
        />

        <div className="flex w-full items-center gap-2">
          <ComposerPrimitive.AddAttachment
            aria-label="Ajouter un fichier texte"
            className="flex size-8 shrink-0 items-center justify-center rounded-md text-[#5b5950] dark:text-[#a3a098] transition-colors hover:bg-[#1a1a18]/5 dark:hover:bg-white/5 hover:text-[#1a1a18] dark:hover:text-[#eee]"
          >
            <PlusIcon width={16} height={16} />
          </ComposerPrimitive.AddAttachment>

          <div className="ml-auto flex items-center gap-1">
            <ModelPicker />
            <ComposerPrimaryAction />
          </div>
        </div>

        <AuiIf condition={(s) => s.composer.attachments.length > 0}>
          <div className="-mx-1 -mb-1 flex flex-row gap-2 overflow-x-auto pt-1">
            <ComposerPrimitive.Attachments>
              {() => <ClaudeAttachment />}
            </ComposerPrimitive.Attachments>
          </div>
        </AuiIf>
      </ComposerPrimitive.Root>
    </div>
  );
};

const ComposerPrimaryAction: FC = () => {
  return (
    <>
      <AuiIf condition={(s) => s.thread.isRunning}>
        <ComposerPrimitive.Cancel
          aria-label="Arrêter la réécriture"
          className="flex size-8 items-center justify-center rounded-md bg-[#c96442] text-white transition-colors hover:bg-[#b1573a]"
        >
          <div className="size-2.5 rounded-[2px] bg-current" />
        </ComposerPrimitive.Cancel>
      </AuiIf>

      <AuiIf
        condition={(s) => !s.thread.isRunning && s.composer.dictation != null}
      >
        <ComposerPrimitive.StopDictation
          className="flex size-8 items-center justify-center rounded-md bg-[#c96442] text-white transition-colors hover:bg-[#b1573a]"
          aria-label="Arrêter la dictée"
        >
          <div className="size-2.5 animate-pulse rounded-[2px] bg-current" />
        </ComposerPrimitive.StopDictation>
      </AuiIf>

      <AuiIf
        condition={(s) =>
          !s.thread.isRunning &&
          s.composer.dictation == null &&
          !s.composer.isEmpty
        }
      >
        <ComposerPrimitive.Send
          aria-label="Réécrire"
          className="flex size-8 items-center justify-center rounded-md bg-[#c96442] text-white transition-colors hover:bg-[#b1573a] disabled:pointer-events-none disabled:opacity-50"
        >
          <ArrowUpIcon width={16} height={16} />
        </ComposerPrimitive.Send>
      </AuiIf>

      <AuiIf
        condition={(s) =>
          !s.thread.isRunning &&
          s.composer.dictation == null &&
          s.composer.isEmpty
        }
      >
        <ComposerPrimitive.Dictate
          className="flex size-8 items-center justify-center rounded-md text-[#5b5950] dark:text-[#a3a098] transition-colors hover:bg-[#1a1a18]/5 dark:hover:bg-white/5 hover:text-[#1a1a18] dark:hover:text-[#eee]"
          aria-label="Dicter le texte"
        >
          <AudioLines className="size-4" />
        </ComposerPrimitive.Dictate>
      </AuiIf>
    </>
  );
};

const ModelPicker: FC = () => {
  const { model, setModel, expertAllowed } = useContext(SessionContext);
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="flex h-8 items-center gap-1 rounded-md px-2.5 text-sm whitespace-nowrap text-[#1a1a18] dark:text-[#eee] transition outline-none hover:bg-[#1a1a18]/5 dark:hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-[#c96442]/40">
        <span className="font-serif">
          {MODELS.find((m) => m.id === model)?.name}
        </span>
        <ChevronDownIcon width={16} height={16} className="opacity-60" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="min-w-64">
        {MODELS.map((m) => {
          const locked = m.id === "expert" && !expertAllowed;
          return (
            <DropdownMenuItem
              key={m.id}
              onClick={() => setModel(m.id)}
              className="flex items-start gap-3"
            >
              <span className="mt-0.5 flex size-4 items-center justify-center text-[#c96442]">
                {m.id === model ? <CheckIcon /> : null}
              </span>
              <span className="flex flex-1 flex-col">
                <span className="text-foreground flex items-center gap-1.5 font-serif text-sm">
                  {m.name}
                  {locked && (
                    <LockIcon className="size-3" aria-label="réservé à Pro" />
                  )}
                </span>
                <span className="text-muted-foreground text-xs">
                  {m.description}
                </span>
              </span>
            </DropdownMenuItem>
          );
        })}
        <DropdownMenuSeparator />
        <DropdownMenuItem asChild className="text-muted-foreground text-sm">
          <a href={href("/plume/facturation")}>Voir les forfaits</a>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

const ModeTabs: FC = () => {
  const { fill } = useContext(SessionContext);
  return (
    <div className="flex flex-wrap items-center justify-center gap-2">
      {SAMPLES.map(({ label, Icon, text }) => (
        <button
          key={label}
          type="button"
          onClick={() => fill(text)}
          className="flex h-8 items-center gap-1.5 rounded-lg border border-[#E5E0D6] dark:border-[#3d3a35] bg-transparent px-3 text-sm whitespace-nowrap text-[#3d3a35] dark:text-[#cdc9be] transition-colors hover:bg-white/60 dark:hover:bg-[#1f1e1b]/60"
        >
          <Icon
            className="size-3.5 text-[#8a8780] dark:text-[#a3a098]"
            aria-hidden="true"
          />
          <span className="font-serif">{label}</span>
        </button>
      ))}
    </div>
  );
};

const ChatMessage: FC = () => {
  return (
    <MessagePrimitive.Root className="group/message relative mx-auto flex w-full max-w-3xl flex-col py-2">
      <AuiIf condition={(s) => s.message.role === "user"}>
        <div className="flex flex-col items-end gap-1">
          <div className="max-w-[80%] rounded-2xl bg-[#E5E0D6] dark:bg-[#393937] px-4 py-2.5 wrap-break-word whitespace-pre-wrap text-[#1a1a18] dark:text-[#eee]">
            <MessagePrimitive.Parts>
              {({ part }) => (part.type === "text" ? <MarkdownText /> : null)}
            </MessagePrimitive.Parts>
          </div>
          <ActionBarPrimitive.Root className="-mt-px flex items-center gap-0.5 opacity-0 transition-opacity group-focus-within/message:opacity-100 group-hover/message:opacity-100">
            <ActionBarPrimitive.Edit
              aria-label="Modifier"
              className={messageActionButtonClassName}
            >
              <PencilIcon width={16} height={16} />
            </ActionBarPrimitive.Edit>
            <ActionBarPrimitive.Copy
              aria-label="Copier"
              className={messageActionButtonClassName}
            >
              <AuiIf condition={(s) => s.message.isCopied}>
                <CheckIcon />
              </AuiIf>
              <AuiIf condition={(s) => !s.message.isCopied}>
                <ClipboardIcon width={16} height={16} />
              </AuiIf>
            </ActionBarPrimitive.Copy>
          </ActionBarPrimitive.Root>
        </div>
      </AuiIf>

      <AuiIf condition={(s) => s.message.role === "assistant"}>
        <div className="flex flex-col">
          <div className="prose font-serif leading-[1.65rem] wrap-break-word text-[#1a1a18] dark:text-[#eee]">
            <MessagePrimitive.Parts>
              {({ part }) => (part.type === "text" ? <MarkdownText /> : null)}
            </MessagePrimitive.Parts>
            <MessagePrimitive.Error>
              <p className="text-[#b1573a]">
                Réécriture interrompue : rien n’a été décompté.
              </p>
            </MessagePrimitive.Error>
          </div>
          <ActionBarPrimitive.Root className="mt-2 flex items-center gap-0.5 opacity-0 transition-opacity group-focus-within/message:opacity-100 group-hover/message:opacity-100">
            <ActionBarPrimitive.Copy
              aria-label="Copier"
              className={messageActionButtonClassName}
            >
              <AuiIf condition={(s) => s.message.isCopied}>
                <CheckIcon />
              </AuiIf>
              <AuiIf condition={(s) => !s.message.isCopied}>
                <ClipboardIcon width={16} height={16} />
              </AuiIf>
            </ActionBarPrimitive.Copy>
            <ActionBarPrimitive.FeedbackPositive
              aria-label="Utile"
              className={messageActionButtonClassName}
            >
              <ThumbsUp className="size-4" />
            </ActionBarPrimitive.FeedbackPositive>
            <ActionBarPrimitive.FeedbackNegative
              aria-label="Pas utile"
              className={messageActionButtonClassName}
            >
              <ThumbsDown className="size-4" />
            </ActionBarPrimitive.FeedbackNegative>
            <ActionBarPrimitive.Reload
              aria-label="Relancer"
              className={messageActionButtonClassName}
            >
              <RefreshCwIcon width={16} height={16} />
            </ActionBarPrimitive.Reload>
          </ActionBarPrimitive.Root>
        </div>
      </AuiIf>
    </MessagePrimitive.Root>
  );
};

const ClaudeAttachment: FC = () => {
  const isImage = useAuiState((s) => s.attachment.type === "image");
  const src = useAttachmentSrc();

  return (
    <AttachmentPrimitive.Root className="group/thumbnail relative">
      <div
        className="overflow-hidden rounded-lg border border-[#E5E0D6] dark:border-[#3d3a35]"
        style={{ width: "80px", height: "80px" }}
      >
        {isImage && src ? (
          <img
            className="h-full w-full object-cover"
            alt="Pièce jointe"
            src={src}
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-white text-[#5b5950] dark:bg-[#2b2a27] dark:text-[#a3a098]">
            <AttachmentPrimitive.unstable_Thumb className="text-xs" />
          </div>
        )}
      </div>
      <AttachmentPrimitive.Remove
        className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-[#1a1a18] text-white dark:bg-white dark:text-[#1a1a18] opacity-0 transition-opacity group-focus-within/thumbnail:opacity-100 group-hover/thumbnail:opacity-100 hover:bg-[#3d3a35]"
        aria-label="Retirer la pièce jointe"
      >
        <XIcon width={12} height={12} />
      </AttachmentPrimitive.Remove>
    </AttachmentPrimitive.Root>
  );
};
