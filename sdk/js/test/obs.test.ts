import { test, expect, beforeEach, afterAll } from "bun:test";
import {
  init,
  captureException,
  captureMessage,
  flush,
  close,
  parseDSN,
  parseStack,
  _reset,
} from "../src/index";

beforeEach(() => {
  _reset();
});

afterAll(() => {
  close();
});

// --- parseDSN ---

test("parseDSN: valid http with port", () => {
  const { endpoint, apiKey } = parseDSN("http://mykey@localhost:8000");
  expect(endpoint).toBe("http://localhost:8000");
  expect(apiKey).toBe("mykey");
});

test("parseDSN: valid https without port", () => {
  const { endpoint, apiKey } = parseDSN("https://secret@example.com");
  expect(endpoint).toBe("https://example.com");
  expect(apiKey).toBe("secret");
});

test("parseDSN: missing key throws", () => {
  expect(() => parseDSN("http://localhost:8000")).toThrow();
});

test("parseDSN: invalid url throws", () => {
  expect(() => parseDSN("not a url")).toThrow();
});

// --- parseStack ---

test("parseStack: V8 format", () => {
  const stack = `Error: boom
    at doStuff (/app/src/index.ts:42:5)
    at main (/app/src/index.ts:10:3)`;
  const frames = parseStack(stack);
  expect(frames).toHaveLength(2);
  expect(frames[0]).toEqual({
    function: "doStuff",
    filename: "/app/src/index.ts",
    lineno: 42,
    colno: 5,
  });
  expect(frames[1]).toEqual({
    function: "main",
    filename: "/app/src/index.ts",
    lineno: 10,
    colno: 3,
  });
});

test("parseStack: anonymous V8 frame", () => {
  const stack = `Error: x
    at /app/index.js:1:1`;
  const frames = parseStack(stack);
  expect(frames).toHaveLength(1);
  expect(frames[0].function).toBe("(anonymous)");
});

test("parseStack: Firefox format", () => {
  const stack = `doStuff@http://localhost:3000/app.js:42:5
main@http://localhost:3000/app.js:10:3`;
  const frames = parseStack(stack);
  expect(frames).toHaveLength(2);
  expect(frames[0].function).toBe("doStuff");
  expect(frames[0].filename).toBe("http://localhost:3000/app.js");
});

test("parseStack: empty/undefined returns empty array", () => {
  expect(parseStack(undefined)).toEqual([]);
  expect(parseStack("")).toEqual([]);
});

// --- captureException ---

test("captureException: returns event ID", () => {
  init({ dsn: "http://key@localhost:9999" });
  const id = captureException(new Error("boom"));
  expect(id).toMatch(
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/
  );
});

test("captureException: without init returns empty string", () => {
  expect(captureException(new Error("noop"))).toBe("");
});

// --- captureMessage ---

test("captureMessage: returns event ID", () => {
  init({ dsn: "http://key@localhost:9999" });
  const id = captureMessage("hello");
  expect(id).toMatch(
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/
  );
});

test("captureMessage: without init returns empty string", () => {
  expect(captureMessage("noop")).toBe("");
});

// --- flush with mock server ---

test("flush: sends buffered events to server", async () => {
  const received: any[] = [];

  const server = Bun.serve({
    port: 0,
    routes: {
      "/api/events": {
        POST: async (req) => {
          received.push(await req.json());
          return new Response(JSON.stringify({ id: "ok" }), { status: 201 });
        },
      },
    },
  });

  init({
    dsn: `http://testkey@localhost:${server.port}`,
    environment: "test",
    serverName: "test-host",
  });

  captureException(new Error("something broke"));
  captureMessage("deploy started");

  const ok = await flush();
  expect(ok).toBe(true);
  expect(received).toHaveLength(2);

  const messages = received.map((e: any) => e.message).sort();
  expect(messages).toEqual(["deploy started", "something broke"]);

  const errorEvent = received.find((e: any) => e.message === "something broke");
  expect(errorEvent.level).toBe("error");
  expect(errorEvent.platform).toBe("javascript");
  expect(errorEvent.environment).toBe("test");
  expect(errorEvent.server_name).toBe("test-host");
  expect(errorEvent.stacktrace).toBeArray();
  expect(errorEvent.stacktrace.length).toBeGreaterThan(0);

  const msgEvent = received.find((e: any) => e.message === "deploy started");
  expect(msgEvent.level).toBe("info");

  server.stop();
});

test("flush: returns true when buffer is empty", async () => {
  init({ dsn: "http://key@localhost:9999" });
  expect(await flush()).toBe(true);
});

// --- unique IDs ---

test("event IDs are unique", () => {
  init({ dsn: "http://key@localhost:9999" });
  const ids = new Set<string>();
  for (let i = 0; i < 100; i++) {
    ids.add(captureMessage(`msg ${i}`));
  }
  expect(ids.size).toBe(100);
});
