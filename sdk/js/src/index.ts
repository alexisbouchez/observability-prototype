export interface Options {
  dsn: string;
  environment?: string;
  serverName?: string;
}

export interface StackFrame {
  filename: string;
  function: string;
  lineno: number;
  colno?: number;
}

interface Event {
  event_id: string;
  level: string;
  message: string;
  stacktrace?: StackFrame[];
  platform: string;
  timestamp: string;
  server_name?: string;
  environment?: string;
  extra?: Record<string, unknown>;
}

let endpoint = "";
let apiKey = "";
let environment = "";
let serverName = "";
let buffer: Event[] = [];
let flushTimer: ReturnType<typeof setTimeout> | null = null;

export function init(options: Options): void {
  const parsed = parseDSN(options.dsn);
  endpoint = parsed.endpoint;
  apiKey = parsed.apiKey;
  environment = options.environment ?? "";
  serverName = options.serverName ?? getHostname();

  // Auto-flush every 5 seconds if there are buffered events
  if (flushTimer) clearInterval(flushTimer);
  flushTimer = setInterval(() => {
    if (buffer.length > 0) flush();
  }, 5000);

  // Allow process to exit without waiting for the timer
  if (typeof flushTimer === "object" && "unref" in flushTimer) {
    flushTimer.unref();
  }
}

export function captureException(err: Error): string {
  if (!endpoint) return "";

  const id = uuid();
  buffer.push({
    event_id: id,
    level: "error",
    message: err.message,
    stacktrace: parseStack(err.stack),
    platform: "javascript",
    timestamp: formatTimestamp(new Date()),
    server_name: serverName,
    environment,
  });

  return id;
}

export function captureMessage(
  msg: string,
  level: "error" | "warning" | "info" = "info"
): string {
  if (!endpoint) return "";

  const id = uuid();
  buffer.push({
    event_id: id,
    level,
    message: msg,
    platform: "javascript",
    timestamp: formatTimestamp(new Date()),
    server_name: serverName,
    environment,
  });

  return id;
}

export async function flush(timeout = 5000): Promise<boolean> {
  if (buffer.length === 0) return true;

  const events = buffer.splice(0);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);

  try {
    await Promise.allSettled(
      events.map((event) =>
        fetch(`${endpoint}/api/events`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-OBS-Key": apiKey,
          },
          body: JSON.stringify(event),
          signal: controller.signal,
        })
      )
    );
    return true;
  } catch {
    return false;
  } finally {
    clearTimeout(timer);
  }
}

export function close(): void {
  if (flushTimer) {
    clearInterval(flushTimer);
    flushTimer = null;
  }
}

/** @internal Exported for testing. */
export function parseDSN(dsn: string): { endpoint: string; apiKey: string } {
  let url: URL;
  try {
    url = new URL(dsn);
  } catch {
    throw new Error(`Invalid DSN: ${dsn}`);
  }

  const key = url.username;
  if (!key) {
    throw new Error("DSN must contain an API key as username");
  }

  const port = url.port ? `:${url.port}` : "";
  return {
    endpoint: `${url.protocol}//${url.hostname}${port}`,
    apiKey: key,
  };
}

/** @internal Exported for testing. */
export function parseStack(stack?: string): StackFrame[] {
  if (!stack) return [];

  const frames: StackFrame[] = [];

  for (const line of stack.split("\n")) {
    // V8 format: "    at funcName (file:line:col)"
    const v8 = line.match(/^\s*at\s+(?:(.+?)\s+\()?(.+?):(\d+):(\d+)\)?$/);
    if (v8) {
      frames.push({
        function: v8[1] || "(anonymous)",
        filename: v8[2],
        lineno: parseInt(v8[3], 10),
        colno: parseInt(v8[4], 10),
      });
      continue;
    }

    // Firefox/Safari format: "funcName@file:line:col"
    const ff = line.match(/^(.+?)@(.+?):(\d+):(\d+)$/);
    if (ff) {
      frames.push({
        function: ff[1] || "(anonymous)",
        filename: ff[2],
        lineno: parseInt(ff[3], 10),
        colno: parseInt(ff[4], 10),
      });
    }
  }

  return frames;
}

function uuid(): string {
  if (typeof crypto !== "undefined" && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  // Fallback for older environments
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
  });
}

function formatTimestamp(d: Date): string {
  return d.toISOString().replace("T", " ").replace(/\.\d{3}Z$/, "");
}

function getHostname(): string {
  try {
    if (typeof process !== "undefined" && process.env?.HOSTNAME) {
      return process.env.HOSTNAME;
    }
    // Node/Bun
    if (typeof require !== "undefined") {
      return require("os").hostname();
    }
  } catch {}
  // Browser or unknown
  if (typeof location !== "undefined") {
    return location.hostname;
  }
  return "";
}

/** @internal For testing. */
export function _reset(): void {
  endpoint = "";
  apiKey = "";
  environment = "";
  serverName = "";
  buffer = [];
  if (flushTimer) {
    clearInterval(flushTimer);
    flushTimer = null;
  }
}
