import * as obs from "../../sdk/js/src/index";

obs.init({
  dsn: "http://test123@localhost:8000",
  environment: "development",
  serverName: "example-app",
});

// Capture a simple message
const msgId = obs.captureMessage("Application started");
console.log(`Sent message event: ${msgId}`);

// Capture an error
try {
  throw new Error("database connection failed: timeout after 30s");
} catch (e) {
  const errId = obs.captureException(e as Error);
  console.log(`Sent error event: ${errId}`);
}

// Capture a warning
const warnId = obs.captureMessage("Disk usage above 80%", "warning");
console.log(`Sent warning event: ${warnId}`);

// Flush before exit
const ok = await obs.flush();
console.log(ok ? "All events flushed successfully" : "Timed out flushing events");

obs.close();
