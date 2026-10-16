import { useState } from "react";
import {
  CreditCard,
  Home,
  MenuIcon,
  MoreHorizontalIcon,
  PanelLeftIcon,
  PlusIcon,
  SearchIcon,
  TrashIcon,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Sheet,
  SheetContent,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { href } from "@/lib/router";
import { cn } from "@/lib/utils";
import { ModeToggle } from "@/components/mode-toggle";
import { usePlume, UsageMeter } from "./plume-app";

/**
 * Thread rail cloned from the assistant-ui Claude demo (CloneThreadShell and
 * ThreadList classes), fed by Plume's own browser-side history.
 */
export function ClaudeRail({ empty }: { empty: boolean }) {
  const [collapsed, setCollapsed] = useState(true);
  return (
    <aside
      className={cn(
        "hidden h-full shrink-0 flex-col overflow-hidden border-r border-[#DCD4C2] bg-[#EAE4D3] dark:border-[#3B3934] dark:bg-[#252420] font-[Public_Sans,ui-sans-serif,system-ui,sans-serif] text-[#1a1a18] dark:text-[#eee] transition-[width] duration-200 md:flex",
        collapsed ? "w-12" : "w-65",
      )}
    >
      <div className="flex h-12 shrink-0 items-center overflow-hidden px-2">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setCollapsed(!collapsed)}
              className="size-8 hover:bg-[#1a1a18]/5 dark:hover:bg-white/5"
              aria-label={
                collapsed ? "Afficher l’historique" : "Masquer l’historique"
              }
            >
              <PanelLeftIcon className="size-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent side="right">
            {collapsed ? "Afficher l’historique" : "Masquer l’historique"}
          </TooltipContent>
        </Tooltip>
        {!collapsed && (
          <span className="ml-2 truncate text-sm font-medium">Textes</span>
        )}
      </div>

      <ThreadListBody collapsed={collapsed} empty={empty} />

      <div
        className={cn(
          "mt-auto flex shrink-0 flex-col gap-0.5 transition-[padding] duration-200",
          collapsed ? "px-2 pb-3" : "p-3",
        )}
      >
        {!collapsed && (
          <div className="mb-2 [&>div]:border-[#DCD4C2] [&>div]:bg-[#F0ECE0] dark:[&>div]:border-[#3B3934] dark:[&>div]:bg-[#2b2a27]">
            <UsageMeter />
          </div>
        )}
        <RailLink
          to="/plume/facturation"
          icon={CreditCard}
          label="Forfait et facturation"
          collapsed={collapsed}
        />
        <RailLink
          to="/plume"
          icon={Home}
          label="Site de Plume"
          collapsed={collapsed}
        />
        <ModeToggle
          side={collapsed ? "right" : "top"}
          align={collapsed ? "end" : "start"}
          className="hover:bg-[#1a1a18]/5 dark:hover:bg-white/5"
        />
      </div>
    </aside>
  );
}

function RailLink({
  to,
  icon: Icon,
  label,
  collapsed,
}: {
  to: string;
  icon: typeof Home;
  label: string;
  collapsed: boolean;
}) {
  const link = (
    <a
      href={href(to)}
      aria-label={collapsed ? label : undefined}
      className={cn(
        "focus-visible:ring-ring/50 flex h-8 items-center gap-2 overflow-hidden rounded-md text-sm whitespace-nowrap outline-none hover:bg-[#1a1a18]/5 dark:hover:bg-white/5 focus-visible:ring-1",
        collapsed ? "w-8 justify-center" : "w-full px-2.5",
      )}
    >
      <Icon className="size-4 shrink-0" aria-hidden="true" />
      {!collapsed && label}
    </a>
  );
  if (!collapsed) return link;
  return (
    <Tooltip>
      <TooltipTrigger asChild>{link}</TooltipTrigger>
      <TooltipContent side="right">{label}</TooltipContent>
    </Tooltip>
  );
}

function ThreadListBody({
  collapsed,
  empty,
  onNavigate,
}: {
  collapsed: boolean;
  empty: boolean;
  onNavigate?: () => void;
}) {
  const { chats } = usePlume();
  const [search, setSearch] = useState("");
  const hasThreads = chats.conversations.length > 0;
  const items = chats.conversations.filter((c) =>
    c.title.toLowerCase().includes(search.trim().toLowerCase()),
  );

  return (
    <div
      className={cn(
        "relative flex flex-1 flex-col gap-0.5 transition-[padding,width] duration-200",
        collapsed
          ? "w-12 overflow-hidden px-2 pt-1"
          : "w-65 overflow-y-auto p-3",
      )}
    >
      <Button
        variant="ghost"
        data-active={empty ? "" : undefined}
        onClick={() => {
          chats.startNew();
          onNavigate?.();
        }}
        className={cn(
          "hover:bg-muted data-active:bg-muted h-8 justify-start gap-2 overflow-hidden rounded-md text-sm font-normal transition-all duration-200",
          collapsed
            ? "w-8 gap-0 px-2 has-[>svg]:px-2"
            : "w-full px-2.5 has-[>svg]:px-2.5",
        )}
        aria-label={collapsed ? "Nouveau texte" : undefined}
      >
        <PlusIcon className="size-4 shrink-0" />
        <span
          className={cn(
            "overflow-hidden whitespace-nowrap transition-all duration-200",
            collapsed ? "max-w-0 opacity-0" : "max-w-24 opacity-100",
          )}
        >
          Nouveau texte
        </span>
      </Button>

      {hasThreads && (
        <div
          aria-hidden={collapsed}
          inert={collapsed}
          className={cn(
            "relative px-0.5 py-1 transition-opacity duration-150",
            collapsed && "pointer-events-none opacity-0",
          )}
        >
          <SearchIcon className="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
          <input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Rechercher un texte"
            aria-label="Rechercher un texte"
            className="placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 h-8 w-full rounded-lg border border-transparent bg-[oklab(0.97_-0.0011_0.0038/0.6)] dark:bg-white/5 ps-8 pe-3 text-sm outline-none focus-visible:ring-[3px]"
          />
        </div>
      )}

      <ul
        aria-hidden={collapsed}
        inert={collapsed}
        className={cn(
          "flex flex-col gap-0.5 transition-[opacity,transform] duration-150",
          collapsed
            ? "pointer-events-none opacity-0"
            : "translate-x-0 opacity-100",
        )}
      >
        {hasThreads && items.length === 0 && (
          <li className="text-muted-foreground px-2.5 py-4 text-sm">
            Aucun texte trouvé.
          </li>
        )}
        {items.map((conversation) => (
          <li
            key={conversation.id}
            data-active={conversation.id === chats.activeId ? "" : undefined}
            className="group hover:bg-muted data-active:bg-muted has-focus-visible:bg-muted has-data-[state=open]:bg-muted relative flex h-8 items-center rounded-md transition-colors"
          >
            <button
              type="button"
              onClick={() => {
                chats.select(conversation.id);
                onNavigate?.();
              }}
              aria-current={
                conversation.id === chats.activeId ? "true" : undefined
              }
              className="focus-visible:ring-ring/50 flex h-full min-w-0 flex-1 items-center rounded-md px-2.5 text-start text-sm outline-none group-hover:pe-9 group-has-focus-visible:pe-9 group-has-data-[state=open]:pe-9 group-data-active:pe-9 focus-visible:ring-1"
            >
              <span className="min-w-0 flex-1 truncate">
                {conversation.title}
              </span>
            </button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="data-[state=open]:bg-accent absolute end-1.5 top-1/2 size-6 -translate-y-1/2 p-0 opacity-0 group-hover:opacity-100 group-has-focus-visible:opacity-100 group-data-active:opacity-100 data-[state=open]:opacity-100"
                >
                  <MoreHorizontalIcon className="size-3.5" />
                  <span className="sr-only">Plus d’options</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent
                side="right"
                align="start"
                sideOffset={6}
                className="min-w-32 rounded-xl p-1.5"
              >
                <DropdownMenuItem
                  variant="destructive"
                  onClick={() => chats.remove(conversation.id)}
                  className="gap-2 rounded-lg px-2.5 py-1.5"
                >
                  <TrashIcon className="size-4" />
                  Supprimer
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Mobile history, as in the demo: a sheet opened from the top-left corner. */
export function ClaudeMobileRail({ empty }: { empty: boolean }) {
  const [open, setOpen] = useState(false);
  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <div className="absolute top-2 left-2 z-20 md:hidden">
        <SheetTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="bg-background/70 size-8"
          >
            <MenuIcon className="size-4" />
            <span className="sr-only">Ouvrir l’historique</span>
          </Button>
        </SheetTrigger>
      </div>
      <SheetContent
        side="left"
        className="flex flex-col gap-0 p-0 font-[Public_Sans,ui-sans-serif,system-ui,sans-serif]"
      >
        <SheetTitle className="flex h-12 shrink-0 items-center px-4 text-sm font-medium">
          Textes
        </SheetTitle>
        <ThreadListBody
          collapsed={false}
          empty={empty}
          onNavigate={() => setOpen(false)}
        />
        <div className="flex flex-col gap-0.5 p-3">
          <UsageMeter />
          <a
            href={href("/plume/facturation")}
            className="mt-2 flex h-8 items-center gap-2 rounded-md px-2.5 text-sm hover:bg-muted"
          >
            <CreditCard className="size-4" aria-hidden="true" />
            Forfait et facturation
          </a>
          <a
            href={href("/plume")}
            className="flex h-8 items-center gap-2 rounded-md px-2.5 text-sm hover:bg-muted"
          >
            <Home className="size-4" aria-hidden="true" />
            Site de Plume
          </a>
          <ModeToggle side="top" align="start" />
        </div>
      </SheetContent>
    </Sheet>
  );
}
