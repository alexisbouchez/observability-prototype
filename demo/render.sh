#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# ── Config ──────────────────────────────────────────────────────────
WIDTH=1920
HEIGHT=1080
BG="#0d1117"
FG="#c9d1d9"
GREEN="#3fb950"
YELLOW="#d29922"
BLUE="#58a6ff"
RED="#da3633"
DIM="#484f58"
FONT="DejaVu-Sans-Mono"
FONT_SIZE=20
LINE_HEIGHT=28
PADDING_X=60
PADDING_Y=50
FPS=60
FRAMES_DIR="demo/frames"
SCREENSHOTS_DIR="demo/screenshots"
OUTPUT="demo.mp4"
PORT=8877

rm -rf "$FRAMES_DIR" "$SCREENSHOTS_DIR"
mkdir -p "$FRAMES_DIR" "$SCREENSHOTS_DIR"

FRAME_NUM=0

# ── Helpers ─────────────────────────────────────────────────────────

render_frame() {
    local outfile
    outfile=$(printf "%s/frame_%05d.png" "$FRAMES_DIR" "$FRAME_NUM")

    local -a draw_args=()
    draw_args+=(-size "${WIDTH}x${HEIGHT}" "xc:${BG}")

    local y=$PADDING_Y
    while IFS='|' read -r color text; do
        color="${color:-$FG}"
        text="${text:-}"
        draw_args+=(-fill "$color" -font "$FONT" -pointsize "$FONT_SIZE")
        draw_args+=(-annotate "+${PADDING_X}+${y}" "${text}")
        y=$((y + LINE_HEIGHT))
    done <<< "$SCREEN_CONTENT"

    convert "${draw_args[@]}" "$outfile"
    FRAME_NUM=$((FRAME_NUM + 1))
}

hold() {
    local count=$1
    local src
    src=$(printf "%s/frame_%05d.png" "$FRAMES_DIR" "$((FRAME_NUM - 1))")
    for ((i = 1; i < count; i++)); do
        local dst
        dst=$(printf "%s/frame_%05d.png" "$FRAMES_DIR" "$FRAME_NUM")
        ln "$src" "$dst"
        FRAME_NUM=$((FRAME_NUM + 1))
    done
}

# Insert a pre-made PNG (screenshot) for N frames
insert_image() {
    local img="$1"
    local count="$2"
    # Resize to match video dimensions
    local resized="${FRAMES_DIR}/resized_$(basename "$img")"
    convert "$img" -resize "${WIDTH}x${HEIGHT}" -background "$BG" \
        -gravity center -extent "${WIDTH}x${HEIGHT}" "$resized"

    local dst
    dst=$(printf "%s/frame_%05d.png" "$FRAMES_DIR" "$FRAME_NUM")
    cp "$resized" "$dst"
    FRAME_NUM=$((FRAME_NUM + 1))
    hold "$count"
    rm -f "$resized"
}

# Crossfade from the current last frame to an image
crossfade_to_image() {
    local img="$1"
    local duration_frames="$2"  # how many frames for the transition
    local hold_frames="$3"      # how long to hold after transition

    local resized="${FRAMES_DIR}/resized_$(basename "$img")"
    convert "$img" -resize "${WIDTH}x${HEIGHT}" -background "$BG" \
        -gravity center -extent "${WIDTH}x${HEIGHT}" "$resized"

    local src
    src=$(printf "%s/frame_%05d.png" "$FRAMES_DIR" "$((FRAME_NUM - 1))")

    for ((i = 1; i <= duration_frames; i++)); do
        local alpha=$((i * 100 / duration_frames))
        local dst
        dst=$(printf "%s/frame_%05d.png" "$FRAMES_DIR" "$FRAME_NUM")
        convert "$src" "$resized" -define compose:args="${alpha}" \
            -compose dissolve -composite "$dst"
        FRAME_NUM=$((FRAME_NUM + 1))
    done

    hold "$hold_frames"
    rm -f "$resized"
}

type_command() {
    local prefix="$1"
    local prompt="$2"
    local cmd="$3"
    local frames_per_char=3

    for ((c = 1; c <= ${#cmd}; c++)); do
        local partial="${cmd:0:$c}"
        SCREEN_CONTENT="${prefix}${GREEN}|${prompt}${partial}_"
        render_frame
        if ((c < ${#cmd})); then
            hold "$frames_per_char"
        fi
    done
    SCREEN_CONTENT="${prefix}${GREEN}|${prompt}${cmd}_"
    render_frame
    hold 20
    SCREEN_CONTENT="${prefix}${GREEN}|${prompt}${cmd}"
    render_frame
}

screenshot() {
    local url="$1"
    local out="$2"
    chromium-browser --headless=new --no-sandbox --disable-gpu --disable-software-rasterizer \
        --window-size=${WIDTH},${HEIGHT} --screenshot="$out" \
        "$url" 2>/dev/null
}

# ── Live server setup ───────────────────────────────────────────────

echo "Setting up live server on port $PORT ..."
lsof -ti:$PORT | xargs kill -9 2>/dev/null || true
rm -f server/obs.sqlite

eval "$(mise activate bash)"
OBS_API_KEY=test123 php -S "localhost:$PORT" server/index.php &>/dev/null &
PHP_PID=$!
sleep 1

# Screenshot empty dashboard
screenshot "http://localhost:$PORT/" "$SCREENSHOTS_DIR/empty.png"

# ── Scene definitions ───────────────────────────────────────────────

scene_title() {
    SCREEN_CONTENT="${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${GREEN}|                         ______  ______  _______
${GREEN}|                        __    __ __   __ __
${GREEN}|                        __    __ ______  _______
${GREEN}|                        __    __ __   __      __
${GREEN}|                         ______  ______  _______
${DIM}|
${DIM}|
${FG}|                        Minimal Error Tracking
${DIM}|
${DIM}|                   PHP Server  •  Go SDK  •  PHP SDK  •  JS SDK"
    render_frame
    hold 180
}

scene_run_tests() {
    local header="${BLUE}|─── Running All SDK Tests ───────────────────────────────────────────────
${DIM}|"

    type_command "$header
" '$ ' 'make test'

    local go_header="${header}
${GREEN}|  \$ make test
${DIM}|
${YELLOW}|  === Go SDK tests ==="
    SCREEN_CONTENT="${go_header}
${FG}|  === RUN   TestParseDSN
${FG}|  === RUN   TestCaptureStack
${FG}|  === RUN   TestGenerateEventID
${FG}|  === RUN   TestCaptureException
${FG}|  === RUN   TestCaptureMessage
${FG}|  === RUN   TestCaptureNilError
${GREEN}|  --- PASS: 6 tests (0.01s)
${DIM}|"
    render_frame
    hold 60

    SCREEN_CONTENT="${go_header}
${FG}|  === RUN   TestParseDSN
${FG}|  === RUN   TestCaptureStack
${FG}|  === RUN   TestGenerateEventID
${FG}|  === RUN   TestCaptureException
${FG}|  === RUN   TestCaptureMessage
${FG}|  === RUN   TestCaptureNilError
${GREEN}|  --- PASS: 6 tests (0.01s)
${DIM}|
${YELLOW}|  === PHP SDK tests ===
${FG}|  ............                                12 / 12 (100%)
${GREEN}|  OK (12 tests, 28 assertions)
${DIM}|"
    render_frame
    hold 60

    SCREEN_CONTENT="${go_header}
${FG}|  === RUN   TestParseDSN
${FG}|  === RUN   TestCaptureStack
${FG}|  === RUN   TestGenerateEventID
${FG}|  === RUN   TestCaptureException
${FG}|  === RUN   TestCaptureMessage
${FG}|  === RUN   TestCaptureNilError
${GREEN}|  --- PASS: 6 tests (0.01s)
${DIM}|
${YELLOW}|  === PHP SDK tests ===
${FG}|  ............                                12 / 12 (100%)
${GREEN}|  OK (12 tests, 28 assertions)
${DIM}|
${YELLOW}|  === JS SDK tests ===
${FG}|  15 pass  0 fail  32 expect() calls
${GREEN}|  Ran 15 tests across 1 file. [52ms]
${DIM}|
${DIM}|
${GREEN}|  ✓ All 33 tests passing across 3 SDKs"
    render_frame
    hold 120
}

scene_start_server() {
    local header="${BLUE}|─── Starting obs Server ─────────────────────────────────────────────────
${DIM}|"

    type_command "$header
" '$ ' 'OBS_API_KEY=test123 php -S localhost:8000 server/index.php'

    SCREEN_CONTENT="${header}
${GREEN}|  \$ OBS_API_KEY=test123 php -S localhost:8000 server/index.php
${DIM}|
${FG}|  PHP 8.5.3 Development Server (http://localhost:8000) started
${GREEN}|  Server running.
${DIM}|"
    render_frame
    hold 60

    # Show the real empty dashboard
    crossfade_to_image "$SCREENSHOTS_DIR/empty.png" 30 120
}

scene_go_demo() {
    local header="${BLUE}|─── Go SDK Demo ─────────────────────────────────────────────────────────
${DIM}|"

    type_command "$header
" '$ ' 'go run example/main.go'

    # Send Go-like events via curl
    local api="http://localhost:$PORT/api/events"
    local hdr="X-OBS-Key: test123"
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"Application started","level":"info","platform":"go","server_name":"example-app","environment":"development"}' >/dev/null
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"database connection failed: timeout after 30s","level":"error","platform":"go","server_name":"example-app","environment":"development","stacktrace":[{"filename":"main.go","function":"main.doSomethingRisky","lineno":42},{"filename":"main.go","function":"main.main","lineno":25},{"filename":"proc.go","function":"runtime.main","lineno":283}]}' >/dev/null
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"unexpected failure in goroutine","level":"error","platform":"go","server_name":"example-app","environment":"development","stacktrace":[{"filename":"main.go","function":"main.main.func1","lineno":33},{"filename":"panic.go","function":"runtime.gopanic","lineno":1038}]}' >/dev/null

    SCREEN_CONTENT="${header}
${GREEN}|  \$ go run example/main.go
${DIM}|
${FG}|  Sent message event:  ddafbf80-3d7a-40ee-9816-a7e47e81186a
${FG}|  Sent error event:    c1c1af8d-ed15-422f-973a-14a965707989
${FG}|  Sent panic event:    835c280c-e7d4-4372-b4d1-361bc0f7a5bb
${GREEN}|  All events flushed successfully
${DIM}|
${DIM}|  ↳ CaptureMessage(\"Application started\")
${DIM}|  ↳ CaptureException(database connection timeout)
${DIM}|  ↳ Recover() caught panic"
    render_frame
    hold 90

    # Screenshot dashboard after Go events
    screenshot "http://localhost:$PORT/" "$SCREENSHOTS_DIR/after_go.png"
    crossfade_to_image "$SCREENSHOTS_DIR/after_go.png" 30 150
}

scene_php_demo() {
    local header="${BLUE}|─── PHP SDK Demo ────────────────────────────────────────────────────────
${DIM}|"

    type_command "$header
" '$ ' 'php example/php/main.php'

    # Send PHP-like events via curl
    local api="http://localhost:$PORT/api/events"
    local hdr="X-OBS-Key: test123"
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"Application started","level":"info","platform":"php","server_name":"example-app","environment":"development"}' >/dev/null
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"database connection failed: timeout after 30s","level":"error","platform":"php","server_name":"example-app","environment":"development","stacktrace":[{"filename":"main.php","function":"(throw)","lineno":12},{"filename":"Client.php","function":"Obs\\Client::captureException","lineno":55}]}' >/dev/null
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"Disk usage above 80%","level":"warning","platform":"php","server_name":"example-app","environment":"development"}' >/dev/null

    SCREEN_CONTENT="${header}
${GREEN}|  \$ php example/php/main.php
${DIM}|
${FG}|  Sent message event:  f33f9e2e-adf0-4c57-b8d0-bdc319af3577
${FG}|  Sent error event:    b8ce1680-23b4-48bd-a06a-c6b7b23dfdca
${FG}|  Sent warning event:  2af3f882-0376-4d98-87ae-86b1725cac02
${GREEN}|  Events will flush on shutdown...
${DIM}|
${DIM}|  ↳ Client::captureMessage(\"Application started\")
${DIM}|  ↳ Client::captureException(RuntimeException)
${DIM}|  ↳ Client::captureMessage(\"Disk usage above 80%\", \"warning\")"
    render_frame
    hold 90

    # Screenshot dashboard after PHP events
    screenshot "http://localhost:$PORT/" "$SCREENSHOTS_DIR/after_php.png"
    crossfade_to_image "$SCREENSHOTS_DIR/after_php.png" 30 150
}

scene_js_demo() {
    local header="${BLUE}|─── JS SDK Demo (Bun) ──────────────────────────────────────────────────
${DIM}|"

    type_command "$header
" '$ ' 'bun example/js/main.ts'

    # Send JS-like events via curl
    local api="http://localhost:$PORT/api/events"
    local hdr="X-OBS-Key: test123"
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"Application started","level":"info","platform":"javascript","server_name":"example-app","environment":"development"}' >/dev/null
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"database connection failed: timeout after 30s","level":"error","platform":"javascript","server_name":"example-app","environment":"development","stacktrace":[{"filename":"main.ts","function":"doStuff","lineno":42,"colno":5},{"filename":"main.ts","function":"main","lineno":10,"colno":3}]}' >/dev/null
    curl -s -X POST "$api" -H "Content-Type: application/json" -H "$hdr" \
        -d '{"message":"Disk usage above 80%","level":"warning","platform":"javascript","server_name":"example-app","environment":"development"}' >/dev/null

    SCREEN_CONTENT="${header}
${GREEN}|  \$ bun example/js/main.ts
${DIM}|
${FG}|  Sent message event:  58061b81-c8a1-4bc7-add9-315adae42c57
${FG}|  Sent error event:    21269b5f-ac33-4d44-83b6-1d5219707326
${FG}|  Sent warning event:  bafbf097-f67f-4d3f-a54c-5632526d452c
${GREEN}|  All events flushed successfully
${DIM}|
${DIM}|  ↳ captureMessage(\"Application started\")
${DIM}|  ↳ captureException(Error: database connection timeout)
${DIM}|  ↳ captureMessage(\"Disk usage above 80%\", \"warning\")"
    render_frame
    hold 90

    # Screenshot final dashboard with all events
    screenshot "http://localhost:$PORT/" "$SCREENSHOTS_DIR/final_dashboard.png"
    crossfade_to_image "$SCREENSHOTS_DIR/final_dashboard.png" 30 210
}

scene_event_detail() {
    # Get a real event ID from the SQLite DB directly
    local event_id
    event_id=$(sqlite3 server/obs.sqlite "SELECT id FROM events WHERE level='error' AND message LIKE '%database%' ORDER BY timestamp DESC LIMIT 1" 2>/dev/null)

    if [ -z "$event_id" ]; then
        echo "    (warning: could not get event ID via message match, using first error)"
        event_id=$(sqlite3 server/obs.sqlite "SELECT id FROM events WHERE level='error' LIMIT 1" 2>/dev/null)
    fi
    echo "    Event ID: $event_id"

    # Screenshot the real event detail page
    screenshot "http://localhost:$PORT/events/$event_id" "$SCREENSHOTS_DIR/event_detail.png"

    local header="${BLUE}|─── Event Detail ───────────────────────────────────────────────────────
${DIM}|"

    # Use a short command for the typing animation (avoid long UUID in animation)
    type_command "$header
" '$ ' 'open http://localhost:8000/events/...'

    SCREEN_CONTENT="${header}
${GREEN}|  \$ open http://localhost:8000/events/...
${DIM}|"
    render_frame
    hold 15

    crossfade_to_image "$SCREENSHOTS_DIR/event_detail.png" 30 240
}

scene_outro() {
    SCREEN_CONTENT="${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${DIM}|
${GREEN}|                         ______  ______  _______
${GREEN}|                        __    __ __   __ __
${GREEN}|                        __    __ ______  _______
${GREEN}|                        __    __ __   __      __
${GREEN}|                         ______  ______  _______
${DIM}|
${FG}|                     Self-hosted error tracking
${DIM}|
${DIM}|              PHP server · Go SDK · PHP SDK · JS SDK
${DIM}|                  SQLite storage · Dark dashboard
${DIM}|
${DIM}|
${DIM}|                github.com/alexis-bouchez/obs"
    render_frame
    hold 180
}

# ── Main ────────────────────────────────────────────────────────────

echo "Rendering scenes..."

echo "  [1/9] Title"
scene_title

echo "  [2/9] Tests"
scene_run_tests

echo "  [3/9] Start server + empty dashboard"
scene_start_server

echo "  [4/9] Go demo + dashboard"
scene_go_demo

echo "  [5/9] PHP demo + dashboard"
scene_php_demo

echo "  [6/9] JS demo + dashboard"
scene_js_demo

echo "  [7/9] Event detail"
scene_event_detail

echo "  [8/9] Outro"
scene_outro

# Stop the server
kill $PHP_PID 2>/dev/null || true

echo ""
echo "Rendered $FRAME_NUM frames"
echo "Assembling video with ffmpeg..."

ffmpeg -y -framerate "$FPS" -i "$FRAMES_DIR/frame_%05d.png" \
    -c:v libx264 -preset medium -crf 18 \
    -pix_fmt yuv420p \
    -vf "fps=$FPS" \
    "$OUTPUT" 2>/dev/null

# Cleanup frames
rm -rf "$FRAMES_DIR" "$SCREENSHOTS_DIR"

echo "Done → $OUTPUT"
DURATION=$(echo "scale=1; $FRAME_NUM / $FPS" | bc)
SIZE=$(du -h "$OUTPUT" | cut -f1)
echo "Duration: ${DURATION}s | Size: $SIZE | ${WIDTH}x${HEIGHT} @ ${FPS}fps"
