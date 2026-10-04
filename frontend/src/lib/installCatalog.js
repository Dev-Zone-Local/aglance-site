import { Cloud, Cpu, Monitor, Server, Terminal } from "lucide-react";

// What can be installed where. Add a target here (and its instructions in the admin panel)
// to make it appear in the installer. `status: "soon"` shows a "Coming soon" card.
export const PRODUCTS = [
  {
    id: "cli",
    name: "AtGlance CLI",
    tagline: "Agent for your Linux / systemd hosts",
    icon: Cpu,
    targets: [
      { id: "linux", name: "Linux server", hint: "Ubuntu / Debian, systemd", icon: Terminal, status: "ready" },
      { id: "aws-vm", name: "AWS EC2 extension", hint: "Self-deploy on cloud VMs", icon: Cloud, status: "soon" },
      { id: "azure-vm", name: "Azure VM extension", hint: "Self-deploy on cloud VMs", icon: Cloud, status: "soon" },
    ],
  },
  {
    id: "console",
    name: "Management Console",
    tagline: "Self-hosted console for your organization",
    icon: Server,
    targets: [
      { id: "linux", name: "Linux (Docker)", hint: "Any Docker host", icon: Terminal, status: "ready" },
      { id: "windows", name: "Windows (Docker)", hint: "Docker Desktop / Windows Server", icon: Monitor, status: "ready" },
      { id: "aws", name: "AWS", hint: "One-click deploy to your account", icon: Cloud, status: "soon" },
      { id: "azure", name: "Azure", hint: "One-click deploy to your subscription", icon: Cloud, status: "soon" },
    ],
  },
];

export const findProduct = (id) => PRODUCTS.find((p) => p.id === id);

/**
 * Build the step list for a product + target from the /api/downloads payload.
 * mode "install" = new installation, "update" = update an existing one.
 * Each step: { key, title, text, markdown, code, checksum, action, link }.
 */
export function buildSteps(productId, targetId, dl, mode = "install") {
  const data = dl?.[productId];
  if (!data) return [];
  const latest = data.latest;
  const os = productId === "cli" ? "linux" : targetId;
  const p = data.platforms?.[os] || {};
  const what = productId === "cli" ? "AtGlance CLI" : "Management Console";
  const label = os === "windows" ? "Windows" : "Linux";
  const steps = [];

  if (mode === "update") {
    steps.push({
      key: "update-version",
      title: "Check the latest version",
      text: latest ? `The latest ${what} is v${latest.version}.${latest.notes ? ` ${latest.notes}` : ""}` : `No ${what} release is published yet.`,
    });
    steps.push({
      key: "update-script",
      title: "Download the update script",
      text: `Get the ${label} update script.`,
      action: p.script_url ? { href: p.script_url, label: "Download update script" } : null,
    });
    if (p.update_steps) steps.push({ key: "update-run", title: "Run the update", markdown: p.update_steps });
    return steps;
  }

  steps.push({
    key: "download",
    title: "Download",
    text: latest ? `Get ${what} v${latest.version}${p.file_name ? ` (${p.file_name})` : ""} for ${label}.` : `Get the ${what} for ${label}.`,
    action: p.url ? { href: p.url, label: `Download ${p.file_name || "installer"}` } : null,
  });
  if (productId === "cli" && latest?.validate_command) {
    steps.push({
      key: "verify",
      title: "Verify the download",
      text: 'Check the SHA-256 checksum. It prints "OK" when the file is genuine.',
      checksum: latest.checksum,
      code: latest.validate_command,
    });
  }
  if (p.install_steps) steps.push({ key: "install", title: "Install", markdown: p.install_steps });
  steps.push(
    productId === "cli"
      ? { key: "licence", title: "Register your host", text: "Configure the CLI and register this host with your Management Console.", link: { to: "/docs/quickstart", label: "Read the quickstart" } }
      : { key: "licence", title: "Activate with a licence", text: "The installer asks for a licence key. Create one, enter the emailed code, and paste the key into the installer.", link: { to: "/dashboard/licences", label: "Manage licences" } },
  );
  return steps;
}
