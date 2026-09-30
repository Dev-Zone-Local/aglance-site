/** @type {import('tailwindcss').Config} */

/*
 * TRANSITIONAL: the site was built on a dark zinc + amber theme. Until every page uses the
 * ag-* tokens below, the zinc and amber scales are remapped onto the light AtGlance palette
 * (design-doc.md) so old classes stay readable: low zinc numbers (old light text) become ink,
 * high numbers (old dark surfaces) become light surfaces, amber becomes teal. Remove these two
 * overrides once no page uses zinc-* / amber-* any more.
 */
const legacyZinc = {
  50: "#14171B",
  100: "#14171B",
  200: "#262A30",
  300: "#3A4048",
  400: "#5B626B",
  500: "#8A9099",
  600: "#A3A9B1",
  700: "#D5DAE0",
  800: "#E6E9EE",
  900: "#F4F6F8",
  950: "#FFFFFF",
};
const legacyAmber = {
  300: "#71F7D4",
  400: "#2CB7D9",
  500: "#2CB7D9",
  600: "#2CB7D9",
};

module.exports = {
  content: ["./src/**/*.{js,jsx,ts,tsx}", "./public/index.html"],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Neue Haas Grotesk Text"', "Inter", '"Helvetica Neue"', "Helvetica", "Arial", "sans-serif"],
        mono: ['"JetBrains Mono"', "ui-monospace", "monospace"],
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
        card: "20px",
        frame: "28px",
        input: "12px",
      },
      boxShadow: {
        ag: "0 8px 30px rgba(17, 20, 24, 0.06)",
        "ag-strong": "0 12px 40px rgba(17, 20, 24, 0.12)",
        "ag-glow": "0 6px 18px rgba(76, 201, 255, 0.35)",
        "ag-pill": "0 4px 14px rgba(76, 201, 255, 0.35)",
        "ag-tab": "0 2px 10px rgba(20, 23, 27, 0.08)",
        "ag-focus": "0 0 0 4px rgba(113, 247, 212, 0.3)",
      },
      backgroundImage: {
        "ag-gradient": "linear-gradient(135deg, #71F7D4 0%, #4CC9FF 100%)",
        "ag-gradient-hover": "linear-gradient(135deg, #5FF0C9 0%, #2FBAF2 100%)",
      },
      letterSpacing: { heading: "-0.02em" },
      colors: {
        ag: {
          sky: "#4CC9FF",
          teal: "#2CB7D9",
          mint: "#71F7D4",
          ink: "#14171B",
          "ink-hover": "#262A30",
          "ink-soft": "#B7BEC6",
          bg: "#EEF1F4",
          surface: "#F4F6F8",
          line: "#E6E9EE",
          muted: "#8A9099",
          subtle: "#5B626B",
          success: "#1FA874",
          "success-soft": "#E3FAF3",
          "success-text": "#137A54",
          danger: "#E45757",
          "danger-soft": "#FDECEC",
          "danger-hover": "#F9D6D6",
          "danger-text": "#B33B3B",
          warning: "#D98A0B",
          "warning-soft": "#FEF4E2",
          "warning-text": "#8A5A06",
          info: "#2CB7D9",
          "info-soft": "#E4F6FF",
          "accent-soft": "#E5FCF6",
        },
        zinc: legacyZinc,
        amber: legacyAmber,
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        card: { DEFAULT: "hsl(var(--card))", foreground: "hsl(var(--card-foreground))" },
        popover: { DEFAULT: "hsl(var(--popover))", foreground: "hsl(var(--popover-foreground))" },
        primary: { DEFAULT: "hsl(var(--primary))", foreground: "hsl(var(--primary-foreground))" },
        secondary: { DEFAULT: "hsl(var(--secondary))", foreground: "hsl(var(--secondary-foreground))" },
        muted: { DEFAULT: "hsl(var(--muted))", foreground: "hsl(var(--muted-foreground))" },
        accent: { DEFAULT: "hsl(var(--accent))", foreground: "hsl(var(--accent-foreground))" },
        destructive: { DEFAULT: "hsl(var(--destructive))", foreground: "hsl(var(--destructive-foreground))" },
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        chart: {
          1: "hsl(var(--chart-1))",
          2: "hsl(var(--chart-2))",
          3: "hsl(var(--chart-3))",
          4: "hsl(var(--chart-4))",
          5: "hsl(var(--chart-5))",
        },
      },
      keyframes: {
        "accordion-down": { from: { height: "0" }, to: { height: "var(--radix-accordion-content-height)" } },
        "accordion-up": { from: { height: "var(--radix-accordion-content-height)" }, to: { height: "0" } },
      },
      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
};
