import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";

/* shadcn/ui "Dark mode" for Vite, with a scope: only the pages that opt in follow the choice. */

export type Theme = "dark" | "light" | "system";

const ThemeContext = createContext<{
  theme: Theme;
  resolved: "dark" | "light";
  setTheme: (theme: Theme) => void;
}>({ theme: "system", resolved: "light", setTheme: () => {} });

export const THEME_STORAGE_KEY = "vite-ui-theme";

export function ThemeProvider({
  children,
  enabled,
  defaultTheme = "system",
}: {
  children: ReactNode;
  /** false keeps the page light: the Établi store and the Plume site have one palette. */
  enabled: boolean;
  defaultTheme?: Theme;
}) {
  const [theme, setThemeState] = useState<Theme>(
    () =>
      (localStorage.getItem(THEME_STORAGE_KEY) as Theme | null) ?? defaultTheme,
  );
  const [systemDark, setSystemDark] = useState(
    () => matchMedia("(prefers-color-scheme: dark)").matches,
  );

  useEffect(() => {
    const query = matchMedia("(prefers-color-scheme: dark)");
    const onChange = () => setSystemDark(query.matches);
    query.addEventListener("change", onChange);
    return () => query.removeEventListener("change", onChange);
  }, []);

  const resolved =
    enabled && (theme === "dark" || (theme === "system" && systemDark))
      ? "dark"
      : "light";

  useEffect(() => {
    const root = document.documentElement;
    root.classList.remove("light", "dark");
    root.classList.add(resolved);
    root.style.colorScheme = resolved;
  }, [resolved]);

  return (
    <ThemeContext.Provider
      value={{
        theme,
        resolved,
        setTheme: (next) => {
          localStorage.setItem(THEME_STORAGE_KEY, next);
          setThemeState(next);
        },
      }}
    >
      {children}
    </ThemeContext.Provider>
  );
}

export const useTheme = () => useContext(ThemeContext);
