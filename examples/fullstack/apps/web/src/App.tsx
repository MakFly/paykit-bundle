import { useEffect } from "react";
import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { ThemeProvider, useTheme } from "@/components/theme-provider";
import { Devtools } from "@/components/shell/devtools";
import { navigate, useRoute } from "@/lib/router";
import { EtabliApp } from "@/modules/etabli/etabli-app";
import { PlumeApp } from "@/modules/plume/plume-app";

const LAST_SITE = "paykit-site";

export default function App() {
  const route = useRoute();
  const site = route.startsWith("/plume")
    ? "plume"
    : route.startsWith("/etabli")
      ? "etabli"
      : null;

  useEffect(() => {
    if (site) localStorage.setItem(LAST_SITE, site);
    else
      navigate(
        `/${localStorage.getItem(LAST_SITE) === "plume" ? "plume" : "etabli"}`,
      );
  }, [site]);

  // Only the Plume app pages follow the light / dark choice.
  const themed = /^\/plume\/(app|facturation)/.test(route);
  return (
    <ThemeProvider enabled={themed}>
      <TooltipProvider>
        {/* Each site keeps its own PayKit customer and state; switching remounts it. */}
        {site === "etabli" && (
          <EtabliApp route={route.slice("/etabli".length)} />
        )}
        {site === "plume" && <PlumeApp route={route.slice("/plume".length)} />}
        <Devtools route={route} />
        <ThemedToaster />
      </TooltipProvider>
    </ThemeProvider>
  );
}

function ThemedToaster() {
  const { resolved } = useTheme();
  return <Toaster position="top-center" theme={resolved} />;
}
