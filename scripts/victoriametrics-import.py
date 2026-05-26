#!/usr/bin/env python3
"""
Import an OpenMetrics export file into VictoriaMetrics.

Reads the file produced by prometheus-export.py and POSTs samples
in batches to VictoriaMetrics's /api/v1/import/prometheus endpoint.

Timestamps are converted from seconds (OpenMetrics) to milliseconds
(VictoriaMetrics import format).

Usage:
  python3 victoriametrics-import.py [--url URL] [--input FILE] [--batch-size N]
"""

import argparse
import sys
import time
import urllib.request


def send_batch(endpoint, lines, retries=5):
    payload = "\n".join(lines) + "\n"
    for attempt in range(retries):
        try:
            req = urllib.request.Request(
                endpoint,
                data=payload.encode(),
                headers={"Content-Type": "text/plain"},
                method="POST",
            )
            with urllib.request.urlopen(req, timeout=60) as resp:
                if resp.status != 204:
                    body = resp.read().decode()
                    raise RuntimeError(f"HTTP {resp.status}: {body}")
                return True
        except Exception as e:
            if attempt == retries - 1:
                raise
            wait = min(2 ** attempt, 30)
            print(f"  Retry {attempt+1}/{retries}: {e} (waiting {wait}s)", file=sys.stderr)
            time.sleep(wait)


def convert_line(line):
    """Convert an OpenMetrics sample line to VictoriaMetrics import format.

    OpenMetrics:  metric{labels} value timestamp_seconds.decimals
    VM import:    metric{labels} value timestamp_milliseconds
    """
    parts = line.rsplit(" ", 2)
    if len(parts) != 3:
        return None

    metric_labels, value, ts_str = parts
    try:
        ts_ms = int(float(ts_str) * 1000)
    except ValueError:
        return None

    return f"{metric_labels} {value} {ts_ms}"


def main():
    parser = argparse.ArgumentParser(description="Import OpenMetrics data into VictoriaMetrics")
    parser.add_argument("--url", default="http://rapunzel.mintopia.net:8428",
                        help="VictoriaMetrics base URL (default: http://rapunzel.mintopia.net:8428)")
    parser.add_argument("--input", "-i", default="prometheus-export.om",
                        help="Input OpenMetrics file (default: prometheus-export.om)")
    parser.add_argument("--batch-size", type=int, default=10000,
                        help="Lines per POST request (default: 10000)")
    parser.add_argument("--delay", type=float, default=0,
                        help="Delay between batches in seconds (default: 0)")
    args = parser.parse_args()

    endpoint = f"{args.url.rstrip('/')}/api/v1/import/prometheus"

    print(f"VictoriaMetrics importer", file=sys.stderr)
    print(f"  Source: {args.input}", file=sys.stderr)
    print(f"  Target: {endpoint}", file=sys.stderr)
    print(f"  Batch size: {args.batch_size}", file=sys.stderr)
    print(file=sys.stderr)

    batch = []
    total_lines = 0
    total_batches = 0
    skipped = 0
    started = time.time()

    with open(args.input, "r") as f:
        for line_num, raw_line in enumerate(f, 1):
            line = raw_line.rstrip("\n")

            if not line or line.startswith("#"):
                continue

            converted = convert_line(line)
            if converted is None:
                skipped += 1
                continue

            batch.append(converted)
            total_lines += 1

            if len(batch) >= args.batch_size:
                total_batches += 1
                print(f"\r  Batch {total_batches}: {total_lines:,} samples sent...",
                      end="", file=sys.stderr)
                sys.stderr.flush()
                send_batch(endpoint, batch)
                batch = []
                if args.delay > 0:
                    time.sleep(args.delay)

    if batch:
        total_batches += 1
        print(f"\r  Batch {total_batches}: {total_lines:,} samples sent...",
              end="", file=sys.stderr)
        send_batch(endpoint, batch)

    elapsed = time.time() - started

    print(file=sys.stderr)
    print(file=sys.stderr)
    print(f"Import complete!", file=sys.stderr)
    print(f"  Total samples: {total_lines:,}", file=sys.stderr)
    print(f"  Batches: {total_batches}", file=sys.stderr)
    print(f"  Skipped lines: {skipped}", file=sys.stderr)
    print(f"  Elapsed: {elapsed:.1f}s", file=sys.stderr)
    print(f"  Rate: {total_lines/elapsed:,.0f} samples/s", file=sys.stderr)


if __name__ == "__main__":
    main()
